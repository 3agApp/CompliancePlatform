<?php

namespace App\Notifications\Products;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a distributor's reviewers that a product is waiting on them.
 *
 * Says whether it is coming back after they asked for changes, and how much
 * of the template is still open, so a reviewer can tell from the inbox
 * whether this is a quick look or a long read.
 */
class ProductSubmittedForReview extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Product $product,
        public Organization $supplier,
        public string $submitterName,
        public bool $isResubmission,
        public int $outstandingRequirements,
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
        $replacements = [
            'submitterName' => $this->submitterName,
            'supplierName' => $this->supplier->name,
            'productName' => $this->product->name,
        ];

        $mail = (new MailMessage)
            ->subject($this->isResubmission
                ? __(':supplierName resubmitted :productName for review', $replacements)
                : __(':supplierName submitted :productName for review', $replacements))
            ->line($this->isResubmission
                ? __(':submitterName at :supplierName made the changes you asked for on :productName and submitted it again.', $replacements)
                : __(':submitterName at :supplierName submitted :productName for review.', $replacements));

        if ($this->outstandingRequirements > 0) {
            $mail->line(trans_choice(
                '1 requirement of its template is still open.|:count requirements of its template are still open.',
                $this->outstandingRequirements,
            ));
        }

        return $mail->action(__('Review the product'), route('products.edit', [
            'current_organization' => $this->product->organization->slug,
            'product' => $this->product->id,
        ]));
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
            'is_resubmission' => $this->isResubmission,
        ];
    }
}
