<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Pterodactyl\Events\Event;
use Illuminate\Container\Container;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Contracts\Core\ReceivesEvents;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\Messages\MailMessage;

class ServerInstalled extends Notification implements ShouldQueue, ReceivesEvents
{
    use Queueable;

    /**
     * @var \Pterodactyl\Models\Server
     */
    public $server;

    /**
     * @var \Pterodactyl\Models\User
     */
    public $user;

    /**
     * Handle a direct call to this notification from the server installed event. This is configured
     * in the event service provider.
     *
     * @param \Pterodactyl\Events\Event|\Pterodactyl\Events\Server\Installed $event
     */
    public function handle(Event $event): void
    {
        $event->server->loadMissing('user');

        $this->server = $event->server;
        $this->user = $event->server->user;

        // Since we are calling this notification directly from an event listener we need to fire off the dispatcher
        // to send the email now. Don't use send() or you'll end up firing off two different events.
        Container::getInstance()->make(Dispatcher::class)->sendNow($this->user, $this);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail()
    {
        return (new MailMessage())
            ->subject('服务器已安装')
            ->greeting('你好 ' . $this->user->username . '.')
            ->line('您的服务器已完成安装，现在可以使用了。')
            ->line('服务器名称: ' . $this->server->name)
            ->action('点此登入并开始使用', route('index'));
    }
}
