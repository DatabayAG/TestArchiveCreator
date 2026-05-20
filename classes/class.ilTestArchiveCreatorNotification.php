<?php

declare(strict_types=1);

class ilTestArchiveCreatorNotification
{
    /** @var array<int, string[]>    login => links */
    private array $notifications = [];

    public function __construct(
        private ilTestArchiveCreatorPlugin $plugin,
        private ilMail $mail
    ) {
    }

    public function addNotification(int $user_id, string $link)
    {
        $this->notifications[$user_id][] = $link;
    }

    public function sendNotifications()
    {
        foreach ($this->notifications as $user_id => $links) {
            $login = ilObjUser::_lookupLogin($user_id);
            $language = ilObjUser::_lookupLanguage($user_id);
            if ($login !== '') {
                $this->mail->enqueue(
                    $login,
                    '',
                    '',
                    $this->plugin->txt('notification_subject', $language),
                    $this->plugin->txt('notification_message', $language) . "\n\n" . implode("\n", $links),
                    []
                );
            }
        }
    }
}
