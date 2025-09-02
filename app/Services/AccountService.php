<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Faculty;
use App\Models\ShsStudent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Create a new account with optional person linking
     */
    public function createAccount(array $accountData, ?Model $person = null): Account
    {
        return DB::transaction(function () use ($accountData, $person) {
            // Validate email uniqueness
            if (Account::where('email', $accountData['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account with this email already exists.',
                ]);
            }

            // Validate username uniqueness
            if (Account::where('username', $accountData['username'])->exists()) {
                throw ValidationException::withMessages([
                    'username' => 'An account with this username already exists.',
                ]);
            }

            // Hash password if provided
            if (isset($accountData['password'])) {
                $accountData['password'] = Hash::make($accountData['password']);
            }

            // Set default values
            $accountData['is_active'] = $accountData['is_active'] ?? true;
            $accountData['is_notification_active'] = $accountData['is_notification_active'] ?? true;

            // Link to person if provided
            if ($person) {
                // For Faculty, we don't use person_id due to UUID vs bigint issue
                if ($person instanceof Faculty) {
                    $accountData['person_type'] = get_class($person);
                    $accountData['person_id'] = null; // Don't set person_id for Faculty
                } else {
                    $accountData['person_id'] = $this->getPersonId($person);
                    $accountData['person_type'] = get_class($person);
                }

                $accountData['role'] = $this->determineRoleFromPerson($person);

                // Use person's email if not provided
                if (! isset($accountData['email']) && $person->email) {
                    $accountData['email'] = $person->email;
                }

                // Use person's name if not provided
                if (! isset($accountData['name'])) {
                    $accountData['name'] = $this->getPersonName($person);
                }
            }

            $account = Account::create($accountData);

            Log::info('Account created successfully', [
                'account_id' => $account->id,
                'email' => $account->email,
                'linked_person' => $person ? get_class($person) : null,
            ]);

            return $account;
        });
    }

    /**
     * Link an account to a person (Student, Faculty, or ShsStudent)
     */
    public function linkAccountToPerson(Account $account, Model $person): Account
    {
        return DB::transaction(function () use ($account, $person) {
            // Validate that the person can be linked
            $this->validatePersonLinking($person);

            // Check if person is already linked to another account
            $existingAccount = $this->findAccountByPerson($person);
            if ($existingAccount && $existingAccount->id !== $account->id) {
                throw ValidationException::withMessages([
                    'person' => 'This person is already linked to another account.',
                ]);
            }

            // Update account with person information
            $updateData = [
                'person_type' => get_class($person),
                'role' => $this->determineRoleFromPerson($person),
                'email' => $account->email ?: $person->email,
                'name' => $account->name ?: $this->getPersonName($person),
            ];

            // For Faculty, we don't use person_id due to UUID vs bigint issue
            if (! ($person instanceof Faculty)) {
                $updateData['person_id'] = $this->getPersonId($person);
            } else {
                $updateData['person_id'] = null;
            }

            $account->update($updateData);

            Log::info('Account linked to person', [
                'account_id' => $account->id,
                'person_type' => get_class($person),
                'person_id' => $this->getPersonId($person),
            ]);

            return $account->fresh();
        });
    }

    /**
     * Unlink an account from its associated person
     */
    public function unlinkAccountFromPerson(Account $account): Account
    {
        return DB::transaction(function () use ($account) {
            if (! $account->person_id || ! $account->person_type) {
                throw ValidationException::withMessages([
                    'account' => 'Account is not linked to any person.',
                ]);
            }

            $oldPersonType = $account->person_type;
            $oldPersonId = $account->person_id;

            $account->update([
                'person_id' => null,
                'person_type' => null,
                'role' => 'guest', // Set to guest role when unlinked
            ]);

            Log::info('Account unlinked from person', [
                'account_id' => $account->id,
                'old_person_type' => $oldPersonType,
                'old_person_id' => $oldPersonId,
            ]);

            return $account->fresh();
        });
    }

    /**
     * Find accounts that are not linked to any person
     */
    public function getUnlinkedAccounts()
    {
        return Account::whereNull('person_id')
            ->orWhereNull('person_type')
            ->get();
    }

    /**
     * Find students without accounts
     */
    public function getStudentsWithoutAccounts()
    {
        return Student::whereDoesntHave('account')->get();
    }

    /**
     * Find faculties without accounts
     */
    public function getFacultiesWithoutAccounts()
    {
        // Since Faculty uses email matching, we need to check differently
        return Faculty::whereNotIn('email', function ($query) {
            $query->select('email')
                ->from('accounts')
                ->where('person_type', Faculty::class)
                ->whereNotNull('email');
        })->get();
    }

    /**
     * Find SHS students without accounts
     */
    public function getShsStudentsWithoutAccounts()
    {
        return ShsStudent::whereDoesntHave('account')->get();
    }

    /**
     * Activate an account
     */
    public function activateAccount(Account $account): Account
    {
        $account->update(['is_active' => true]);

        Log::info('Account activated', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Deactivate an account
     */
    public function deactivateAccount(Account $account): Account
    {
        $account->update(['is_active' => false]);

        Log::info('Account deactivated', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Reset account password
     */
    public function resetPassword(Account $account, string $newPassword): Account
    {
        $account->update([
            'password' => Hash::make($newPassword),
        ]);

        Log::info('Account password reset', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Get person ID based on person type
     */
    private function getPersonId(Model $person): mixed
    {
        if ($person instanceof Student) {
            return $person->id;
        }

        if ($person instanceof Faculty) {
            return $person->id; // Faculty uses UUID
        }

        if ($person instanceof ShsStudent) {
            return $person->student_lrn; // SHS uses LRN
        }

        throw new \InvalidArgumentException('Unsupported person type: '.get_class($person));
    }

    /**
     * Get person name based on person type
     */
    private function getPersonName(Model $person): string
    {
        if ($person instanceof Student) {
            return trim("{$person->first_name} {$person->middle_name} {$person->last_name}");
        }

        if ($person instanceof Faculty) {
            return $person->getFullNameAttribute();
        }

        if ($person instanceof ShsStudent) {
            return $person->fullname ?? 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * Determine role from person type
     */
    private function determineRoleFromPerson(Model $person): string
    {
        if ($person instanceof Student || $person instanceof ShsStudent) {
            return 'student';
        }

        if ($person instanceof Faculty) {
            return 'faculty';
        }

        return 'guest';
    }

    /**
     * Validate that a person can be linked to an account
     */
    private function validatePersonLinking(Model $person): void
    {
        if (! ($person instanceof Student) &&
            ! ($person instanceof Faculty) &&
            ! ($person instanceof ShsStudent)) {
            throw new \InvalidArgumentException('Person must be a Student, Faculty, or ShsStudent');
        }
    }

    /**
     * Find account by person
     */
    private function findAccountByPerson(Model $person): ?Account
    {
        $personType = get_class($person);

        if ($person instanceof Faculty) {
            // For Faculty, match by email and person_type
            return Account::where('email', $person->email)
                ->where('person_type', $personType)
                ->first();
        }

        // For other person types, use person_id
        $personId = $this->getPersonId($person);

        return Account::where('person_id', $personId)
            ->where('person_type', $personType)
            ->first();
    }
}
