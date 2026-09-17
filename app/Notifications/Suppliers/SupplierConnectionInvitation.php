<?php

namespace App\Notifications\Suppliers;

use App\Models\SupplierConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierConnectionInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public SupplierConnection $supplierConnection)
    {
        //
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
        $distributor = $this->supplierConnection->distributorOrganization;
        $inviter = $this->supplierConnection->inviter;

        return (new MailMessage)
            ->subject(__(':distributorName wants to work with you on product compliance', [
                'distributorName' => $distributor->name,
            ]))
            ->line(__(':inviterName from :distributorName has invited :companyName to supply them.', [
                'inviterName' => $inviter->name,
                'distributorName' => $distributor->name,
                'companyName' => $this->supplierConnection->company_name,
            ]))
            ->line(__('Accepting gives you access to the products they assign to you, so you can complete their compliance details.'))
            ->action(
                __('Review invitation'),
                route('login', ['invitation' => $this->supplierConnection->code]),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'supplier_connection_id' => $this->supplierConnection->id,
            'distributor_organization_id' => $this->supplierConnection->distributor_organization_id,
            'distributor_organization_name' => $this->supplierConnection->distributorOrganization->name,
            'company_name' => $this->supplierConnection->company_name,
        ];
    }
}
