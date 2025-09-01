<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentEnrolledVerified extends Notification
{
    use Queueable;

    // public $student_name;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $record)
    {
        // $this->student_name = $student_name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subjects = $this->record->SubjectsEnrolled;
        $subjectTable = '<table style="width:100%; border-collapse: collapse;">';
        $subjectTable .= '<tr><th style="border: 1px solid #ddd; padding: 8px;">Subject Code</th><th style="border: 1px solid #ddd; padding: 8px;">Title</th><th style="border: 1px solid #ddd; padding: 8px;">Units</th><th style="border: 1px solid #ddd; padding: 8px;">Lec</th><th style="border: 1px solid #ddd; padding: 8px;">Lab</th></tr>';
        foreach ($subjects as $subject) {
            $subjectDetails = $subject->subject;
            $subjectTable .= '<tr>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 8px;">'.$subjectDetails->code.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 8px;">'.$subjectDetails->title.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 8px;">'.$subjectDetails->units.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 8px;">'.$subjectDetails->lecture.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 8px;">'.$subjectDetails->laboratory.'</td>';
            $subjectTable .= '</tr>';
        }

        $subjectTable .= '</table>';

        $invoice = $this->record->studentTuition;
        $invoiceTable = '<table style="width:100%; border-collapse: collapse; margin-top: 20px;">';
        $invoiceTable .= '<tr><th style="border: 1px solid #ddd; padding: 8px;">Description</th><th style="border: 1px solid #ddd; padding: 8px;">Amount</th></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 8px;">Tuition Fee</td><td style="border: 1px solid #ddd; padding: 8px;">'.$invoice->total_tuition.'</td></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 8px;">Miscellaneous Fee</td><td style="border: 1px solid #ddd; padding: 8px;">'.$invoice->total_miscelaneous_fees.'</td></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 8px;">Discount</td><td style="border: 1px solid #ddd; padding: 8px;">'.$invoice->discount.'</td></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 8px;">Total</td><td style="border: 1px solid #ddd; padding: 8px;">'.$invoice->overall_tuition.'</td></tr>';
        $invoiceTable .= '</table>';
        // $signimage = '<img src="'.$this->record->signature->depthead_signature.'" class="rounded-xl m-4 w-full"> </img> ';

        return (new MailMessage)
            ->subject('Enrollment Verified')
            ->greeting('Hello, '.$this->record->student_name)
            ->line('We are pleased to inform you that your enrollment has been successfully verified.')
            ->line('Here are the subjects you are enrolled in:')
            ->line(new \Illuminate\Support\HtmlString($subjectTable))
            ->line('Below is your total fees:')
            ->line(new \Illuminate\Support\HtmlString($invoiceTable))
            ->line('Please come to the school within 3 to 5 days to pay for your downpayment of '.$this->record->downpayment)
            ->line('Thank you for choosing our institution!')
            ->line('Best wishes,')
            ->salutation('The Head of Department');
        // ->line(new \Illuminate\Support\HtmlString($signimage));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
