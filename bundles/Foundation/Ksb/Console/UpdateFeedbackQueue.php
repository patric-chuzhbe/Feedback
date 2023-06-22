<?php

namespace Bundles\Foundation\Ksb\Console;

use Bundles\Foundation\Framework\Console\Command;
use Models\FeedbackQueue;
use Bundles\Foundation\Ksb\Lib\Feedback as FeedbackLib;
use KsbSlim, SimpleXMLElement, Enjoin;

class UpdateFeedbackQueue extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ksb:update-feedback-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update feedback queue...';

    /**
     * Handle...
     */
    public function handle()
    {
        Enjoin::get('FeedbackQueue')->bulkCreate($this->loadNewQueueItems());
    }

    /**
     * @return array
     */
    protected function loadNewQueueItems(): array
    {
        $out = [];
        $presentCustomers = [];
        $customers = simplexml_load_string($this->getPersonalCustomerList())->PersonalCustomer ?? [];
        $existed = $this->getExisted($customers);
        $products = $this->getProducts($customers);
        foreach ($customers as $customer) {
            $customerHash = $customer['EndDate'] . ' 00:00:00' . $customer['EMail'];
            if (!isset($presentCustomers[$customerHash])
                && !isset($existed[$customerHash])
                && isset($products[(string)$customer['HotelShortName']])) {
                $presentCustomers[$customerHash] = true;
                $out [] = [
                    'end_date' => $customer['EndDate'],
                    'status' => FeedbackQueue::AWAIT_STATUS,
                    'email' => $this->parseCustomerEmailsString((string)$customer['EMail']),
                    'pages_id' => $products[(string)$customer['HotelShortName']],
                    'first_name' => mb_convert_case((string)$customer['FirstName'], MB_CASE_TITLE),
                    'middle_name' => mb_convert_case((string)$customer['MiddleName'], MB_CASE_TITLE),
                    'last_name' => mb_convert_case((string)$customer['LastName'], MB_CASE_TITLE),
                    'tries_counter' => 0,
                    'token' => (new FeedbackLib)->getSecurityToken()
                ];
            }
        }
        return $out;
    }

    /**
     * @param SimpleXMLElement|array $customers
     * @return array
     */
    protected function getExisted($customers): array
    {
        $where = [
            'email' => [],
            'end_date' => []
        ];
        foreach ($customers as $customer) {
            $where['email'] [] = (string)$customer['EMail'];
            $where['end_date'] [] = (string)$customer['EndDate'];
        }
        $existed = Enjoin::get('FeedbackQueue')->findAll([
            'where' => $where,
            'attributes' => ['email', 'end_date']
        ]);

        $out = [];
        foreach ($existed as &$it) {
            $out[$it->end_date . $it->email] = true;
        }
        return $out;
    }

    /**
     * @return string
     */
    protected function getPersonalCustomerList(): string
    {
        $slim = new KsbSlim(['baseUrl' => config('ksb.ems_servers.0')]);
        $slim->setAccount(config('ksb.ems_acc'));
        $sid = $slim->getSession();
        try {
            $out = $slim->EMS('GetPersonalCustomerList', [$sid]);
        } finally {
            $slim->callLogout($sid);
        }
        return $out;
    }

    /**
     * @param SimpleXMLElement|array $customers
     * @return array
     */
    protected function getProducts($customers): array
    {
        $products = Enjoin::get('Pages')->findAll([
            'where' => [
                'ksb_code' => array_map(function ($customer) {
                    return (string)$customer['HotelShortName'];
                }, is_array($customers) ? $customers : iterator_to_array($customers, false))
            ],
            'attributes' => ['id', 'ksb_code']
        ]);
        $out = [];
        foreach ($products as &$product) {
            $out[$product->ksb_code] = $product->id;
        }
        return $out;
    }

    /**
     * @param string $email
     * @return string
     */
    protected function parseCustomerEmailsString(string $email): string
    {
        return preg_split('/\s*[,;]\s*/', $email)[0] ?? '';
    }

}
