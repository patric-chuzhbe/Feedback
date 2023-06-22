<?php

namespace Bundles\Foundation\Ksb\Lib;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Mail, Enjoin;
use Bundles\Foundation\Mindbox\Lib\MindboxClient;

class Feedback
{
    protected $options = [
        'tokenLength' => 32
    ];

    /**
     * @param \Enjoin\Record\Record $job
     */
    public function sendEmail(Enjoin\Record\Record $job): void
    {
        Mail::send('Foundation/Ksb/src/feedback/feedback', [
            'first_name' => $job->first_name,
            'middle_name' => $job->middle_name,
            'last_name' => $job->last_name,
            'product_type' => $job->page->type,
            'product_name' => $job->page->name,
            'link' => $this->getLink($job)
        ], function ($message) use ($job) {
            $message->from(config('mail.no_reply.alean'))
                ->to($job->email)
                ->subject('Туроператор АЛЕАН: пожалуйста, оставьте отзыв');
        });
    }
    /**
     * @param \Enjoin\Record\Record $job
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadToMindbox(Enjoin\Record\Record $job):array
    {
        $client = new MindboxClient([RequestOptions::DELAY => 0]);
        $payload = [
            'customer' => ['email' => $job->email],
            'emailMailing' => [
                'customParameters' => [
                    'DomOtdyha' => $job->page->name,
                    'LinkToVote' => $this->getLink($job, true)
                ]
            ]
        ];

        $url = 'operations/sync?endpointId='.$client->getEndpointId().'&operation=Otzyv';
        try {
            $response = $client->request('POST', $url, [RequestOptions::JSON => $payload]);
            return json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            return [];
        }
    }

    /**
     * @return string
     */
    public function getSecurityToken(): string
    {
        return bin2hex(openssl_random_pseudo_bytes($this->options['tokenLength'] / 2));
    }

    /**
     * @param \Enjoin\Record\Record $job
     * @return string
     */
    protected function getLink(Enjoin\Record\Record $job, $real = false): string
    {
        $host = $real? 'https://www.alean.ru/' : env('APP_URL');
        return $host . 'feedback/?id=' . $job->id . '&token=' . $job->token;
    }

}
