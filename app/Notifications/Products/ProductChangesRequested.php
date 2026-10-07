<?php

namespace App\Notifications\Products;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a supplier that the distributor sent a product back, and what to do.
 *
 * The note is the whole point: it is what the supplier is about to go and
 * do, so the mail carries it word for word instead of asking them to log in
 * to find out whether it matters.
 */
class ProductChangesRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Product $product,
        public Organization $supplier,
        public string $reviewerName,
        public string $note,
    ) {
        $this->afterCommit();
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
        $distributor = $this->product->organization;

        $mail = (new MailMessage)
            ->subject(__(':distributorName sent :productName back with changes to make', [
                'distributorName' => $distributor->name,
                'productName' => $this->product->name,
            ]))
            ->line(__(':reviewerName at :distributorName reviewed :productName and asked for the following changes:', [
                'reviewerName' => $this->reviewerName,
                'distributorName' => $distributor->name,
                'productName' => $this->product->name,
            ]));

        /**
         * One mail line per line of the note. A mail line folds its own line
         * breaks into spaces, which would run a numbered list of what to
         * send back into a single sentence.
         */
        foreach (preg_split('/\R+/', trim($this->note)) ?: [] as $line) {
            $mail->line($line);
        }

        return $mail
            ->action(__('Open the product'), route('products.edit', [
                'current_organization' => $this->supplier->slug,
                'product' => $this->product->id,
            ]))
            ->line(__('Make the changes, then submit the product for review again.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'supplier_organization_id' => $this->supplier->id,
        ];
    }
}
