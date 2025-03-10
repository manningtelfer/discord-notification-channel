<?php

namespace Awssat\Notifications\Channels;

use Awssat\Notifications\Messages\DiscordEmbed;
use Awssat\Notifications\Messages\DiscordEmbedField;
use Awssat\Notifications\Messages\DiscordMessage;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class DiscordWebhookChannel
{

    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $http;

    /**
     * Create a new Slack channel instance.
     *
     * @param  \GuzzleHttp\Client  $http
     * @return void
     */
    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $url = $notifiable->routeNotificationFor('discord', $notification)) {
            return;
        }

        $message = $notification->toDiscord($notifiable);

        if($message instanceof SlackMessage) {
            $slackWebhook = new SlackWebhookChannel($this->http);
            return $this->http->post($url . '/slack', $slackWebhook->buildJsonPayload($message));
        }

        return $this->http->post($url, $this->buildJsonPayload($message));
    }

    /**
     * Build up a JSON payload for the Discord webhook.
     *
     * @param DiscordMessage $message
     * @return array
     */
    public function buildJsonPayload(DiscordMessage $message)
    {
        $optionalFields = array_filter([
            'username' => data_get($message, 'username'),
            'avatar_url' => data_get($message, 'avatar_url'),
            'tts' => data_get($message, 'tts'),
            'timestamp' => data_get($message, 'timestamp'),
            'thread_name' => data_get($message, 'threadname'),
        ]);

        return array_merge([
            'json' => array_merge([
                'content' => $message->content,
                'embeds' => $this->embeds($message),
            ], $optionalFields),
        ], $message->http);
    }


    /**
     * Format the message's embedded content.
     *
     * @param DiscordMessage $message
     *
     * @return array
     */
    protected function embeds(DiscordMessage $message)
    {
        return collect($message->embeds)->map(function (DiscordEmbed $embed) {
            return array_filter([
                'color' => $embed->color,
                'title' => $embed->title,
                'description' => $embed->description,
                'url' => $embed->url,
                'thumbnail' => $embed->thumbnail,
                'image' => $embed->image,
                'footer' => $embed->footer,
                'author' => $embed->author,
                'fields' => $this->embedFields($embed),
            ]);
        })->all();
    }

    protected function embedFields(DiscordEmbed $embed)
    {
        return collect($embed->fields)->map(function ($value, $key) {
            if ($value instanceof DiscordEmbedField) {
                return $value->toArray();
            }

            return ['name' => $key, 'value' => $value, 'inline' => true];
        })->values()->all();
    }
}
