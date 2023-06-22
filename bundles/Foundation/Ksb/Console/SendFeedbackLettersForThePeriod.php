<?php

namespace Bundles\Foundation\Ksb\Console;

use Bundles\Foundation\Framework\Console\Command;
use Models\FeedbackQueue;
use Bundles\Foundation\Ksb\Lib\Feedback as FeedbackLib;
use Carbon\Carbon;
use KsbSlim, SimpleXMLElement, Enjoin, DB;

class SendFeedbackLettersForThePeriod extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ksb:send-feedback-letters-for-the-period
                            {begin : begin date of the period (ex. "2019-07-14")}
                            {end : end date of the period (ex. "2019-07-16")}
                            {--debug : Enable debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends letters about feedback to the customers whose tour endings are included in the period';

    const TIME_POSTFIX = ' 00:00:00';
    const DATE_FORMAT = 'Y-m-d';

    /**
     * Handle...
     */
    public function handle()
    {
        $end = Carbon::parse($this->argument('end'));
        $lib = new FeedbackLib;
        $presentCustomers = [];
        $datesToIterate = $this->getDatesToIterate(Carbon::parse($this->argument('begin')), Carbon::parse($this->argument('end')));
        foreach ($datesToIterate as $date) {
            $this->debug("Processing {$date->format(self::DATE_FORMAT)}...");
            $customers = $this->getPersonalCustomerList($date);
            $products = $this->getProducts($customers, ['attributes' => ['type', 'name']]);
            $customers = $this->convertCustomersToArray($customers);
            $this->filterAlreadyHandledCustomers($customers);
            foreach ($customers as &$customer) {
                $this->debug("Processing {$customer['email']}...");
                $customerHash = $this->getCustomerHash($customer);
                if (!isset($presentCustomers[$customerHash]) && isset($products[$customer['hotelShortName']])) {
                    $presentCustomers[$customerHash] = true;
                    $customer['status'] = FeedbackQueue::AWAIT_STATUS;
                    $customer['pages_id'] = $products[$customer['hotelShortName']]->id;
                    $customer['tries_counter'] = 0;
                    $customer['token'] = (new FeedbackLib)->getSecurityToken();
                    $customer['page'] = $products[$customer['hotelShortName']];
                    $queueItem = Enjoin::get('FeedbackQueue')->create($customer);

                    try {
                        $lib->sendEmail($queueItem);
                    } catch (\Swift_RfcComplianceException $e) {
                        # Ignore incorrect email addresses according to RFC
                        continue;
                    }

                    $queueItem->update(['status' => FeedbackQueue::SENT_STATUS, 'tries_counter' => 1]);
                }
            }
        }
    }

    /**
     * @param \Carbon\Carbon|null $date
     * @return array|SimpleXMLElement
     */
    protected function getPersonalCustomerList(Carbon $date = null)
    {
        $slim = new KsbSlim(['baseUrl' => config('ksb.ems_servers.0')]);
        $slim->setAccount(config('ksb.ems_acc'));
        $sid = $slim->getSession();
        try {
            $out = $slim->EMS('GetPersonalCustomerList', array_merge(
                [$sid],
                $date ? [$date->format(self::DATE_FORMAT)] : []
            ));
        } finally {
            $slim->callLogout($sid);
        }
        return simplexml_load_string($out)->PersonalCustomer ?? [];
    }

    /**
     * @param SimpleXMLElement|array $customers
     * @param array $options
     * @return array
     */
    protected function getProducts($customers, array $options = []): array
    {
        $products = Enjoin::get('Pages')->findAll([
            'where' => [
                'ksb_code' => array_map(function ($customer) {
                    return (string)$customer['HotelShortName'];
                }, is_array($customers) ? $customers : iterator_to_array($customers, false))
            ],
            'attributes' => array_merge(['id', 'ksb_code'], $options['attributes'] ?? [])
        ]);
        $out = [];
        foreach ($products ?? [] as $product) {
            $out[$product->ksb_code] = $options['attributes'] ? $product : $product->id;
        }
        return $out;
    }

    /**
     * @param array $customers
     */
    protected function filterAlreadyHandledCustomers(array &$customers): void
    {
        $HandledCustomersIndex = $this->indexHandledCustomers($customers);
        foreach ($customers as $i => &$customer) {
            if (isset($HandledCustomersIndex[$this->getCustomerHash($customer)])) {
                unset($customers[$i]);
            }
        }
    }

    /**
     * @param array $customers
     * @return array
     */
    protected function indexHandledCustomers(array $customers): array
    {
        $out = [];
        if (count($customers)) {
            $customersEmailsAndDates = array_map(function ($customer) {
                return [$customer['email'], $customer['end_date']];
            }, $customers);
            $emailAndEndDatePlaces = join(',', array_fill(0, count($customersEmailsAndDates), '(?,?)'));
            $sql = <<<EOD
SELECT email, end_date
FROM feedback_queue
WHERE (email, end_date) in ($emailAndEndDatePlaces)
EOD;
            $r = DB::select($sql, call_user_func_array('array_merge', $customersEmailsAndDates));
            foreach ($r ?? [] as $row) {
                $out[$this->getCustomerHash((array)$row)] = true;
            }
        }
        return $out;
    }

    /**
     * @param array $customer
     * @return string
     */
    protected function getCustomerHash(array $customer): string
    {
        return $customer['email']
            . $customer['end_date']
            . (strpos($customer['end_date'], self::TIME_POSTFIX) === false ? self::TIME_POSTFIX : '');
    }

    /**
     * @param SimpleXMLElement|array $customers
     * @return array
     */
    protected function convertCustomersToArray($customers): array
    {
        $out = [];
        foreach ($customers as $customer) {
            $out [] = [
                'end_date' => (string)$customer['EndDate'],
                'email' => $this->parseCustomerEmailsString((string)$customer['EMail']),
                'hotelShortName' => (string)$customer['HotelShortName'],
                'first_name' => mb_convert_case((string)$customer['FirstName'], MB_CASE_TITLE),
                'middle_name' => mb_convert_case((string)$customer['MiddleName'], MB_CASE_TITLE),
                'last_name' => mb_convert_case((string)$customer['LastName'], MB_CASE_TITLE),
            ];
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

    /**
     * @param \Carbon\Carbon $begin
     * @param \Carbon\Carbon $end
     * @return array
     */
    protected function getDatesToIterate(Carbon $begin, Carbon $end): array
    {
        $out = [];
        $handledRanges = $this->getHandledRanges();
        for ($date = $begin; $date->lte($end); $date->addDay()) {
            for (; $this->isDateFallsWithinOneOfTheRanges($date, $handledRanges); $date->addDay()) ;
            $out [] = $date->copy();
        }
        return $out;
    }

    /**
     * @return array
     */
    protected function getHandledRanges(): array
    {
        $sql = <<<EOD
SELECT DISTINCT end_date
FROM feedback_queue
ORDER BY end_date
EOD;
        $r = DB::select($sql);
        $out = [];
        for ($i = 0; $i < count($r); ++$i) {
            $prev = $r[$i - 1]->end_date ?? null;
            $next = $r[$i + 1]->end_date ?? null;
            $out[$r[$i]->end_date] = [
                'prev' => $prev && Carbon::parse($prev)->addDay()->eq($r[$i]->end_date) ? $prev : null,
                'next' => $next && Carbon::parse($next)->subDay()->eq($r[$i]->end_date) ? $next : null
            ];
        }
        return $out;
    }

    /**
     * @param \Carbon\Carbon $date
     * @param array $handledRanges
     * @return bool
     */
    protected function isDateFallsWithinOneOfTheRanges(Carbon $date, array &$handledRanges): bool
    {
        $handledDate = $handledRanges[$date->format(self::DATE_FORMAT) . self::TIME_POSTFIX] ?? null;
        return !is_null($handledDate)
            && !is_null($handledDate['prev'])
            && !is_null($handledDate['next']);
    }

}
