<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\VisibilityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class VisibilityUpAfterDarkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly VisibilityAlert $alert,
        private readonly ?string $objectName,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->objectName ?: $this->alert->object_id;

        return (new MailMessage)
            ->subject(__('Solar alert: :name is up after dark', ['name' => $name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('You asked to be notified when :object is up after dark from :lat, :lon.', [
                'object' => $name,
                'lat' => number_format((float) $this->alert->latitude, 2),
                'lon' => number_format((float) $this->alert->longitude, 2),
            ]))
            ->action(__('Open object page'), route('objects.show', $this->alert->object_id))
            ->line(__('You can remove this alert from your account alerts page at any time.'));
    }
}
