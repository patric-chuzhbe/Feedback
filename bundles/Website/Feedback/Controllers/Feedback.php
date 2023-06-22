<?php

namespace Bundles\Website\Feedback\Controllers;

use Bundles\Foundation\Framework\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Models\FeedbackTypes;
use Models\FeedbackQueue;
use Enjoin\Record\Record;
use Enjoin, DB;

class Feedback extends Controller
{

    protected $options = ['maxRating' => 5];

    /**
     * @param Request $req
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $req)
    {
        $page_title = 'АЛЕАН: Оцените ваши впечатления о поездке';
        return view('Website/Feedback/src/feedback', [
            'id' => $req->get('id'),
            'token' => $req->get('token'),
            'page_title' => $page_title,
            'page_description' => $page_title
        ]);
    }

    /**
     * @param Request $req
     * @return array
     */
    public function load(Request $req): array
    {
        $page = Enjoin::get('Pages')->findOne([
            'attributes' => ['name', 'type'],
            'include' => [
                'model' => Enjoin::get('FeedbackQueue'),
                'where' => ['id' => $req->get('feedbackJobId')],
                'attributes' => ['id', 'status'],
                'required' => true
            ]
        ]);
        if ($page
            && ($page->feedbackQueues[0]->status ?? FeedbackQueue::COMMITTED_STATUS)
            !== FeedbackQueue::COMMITTED_STATUS) {
            return [
                'title' => $page->name,
                'criteria' => Enjoin::get('FeedbackTypes')->findAll([
                    'where' => [
                        'product_type' => $page->type,
                        'tier' => FeedbackTypes::NUM_GRADE_TYPE
                    ],
                    'order' => 'weight'
                ]),
                'id' => $req->get('feedbackJobId'),
                'product' => $page,
                'maxRating' => $this->options['maxRating']
            ];
        }
        return ['notFound' => true];
    }

    /**
     * @param Request $req
     */
    public function save(Request $req): void
    {
        DB::transaction(function () use ($req) {
            $record = Enjoin::get('FeedbackQueue')->findOne([
                'where' => [
                    'id' => $req->get('feedbackJobId'),
                    'status' => ['ne' => FeedbackQueue::COMMITTED_STATUS],
                    'token' => $req->get('token')
                ],
                'attributes' => ['id']
            ]);
            if ($record) {
                $feedbackQueueJob = null;
                $input = $req->all([
                    'feedbackJobId',
                    'criteria',
                    'textNote',
                    'satisfaction'
                ]);
                $pagesRatings = $this->getPagesRatings($input, $feedbackQueueJob);
                Enjoin::get('RepliesFeedbackTypes')->bulkCreate($pagesRatings);
                $this->dealWithRepliesAndRatersTables(
                    $input,
                    $feedbackQueueJob,
                    $pagesRatings,
                    DB::select('SELECT LAST_INSERT_ID() AS insertId')[0]->insertId ?? null
                );
                Enjoin::get('FeedbackQueue')->update(
                    ['status' => FeedbackQueue::COMMITTED_STATUS],
                    ['where' => ['id' => $req->get('feedbackJobId')]]
                );
            }
        });
    }

    /**
     * @param array $args
     * @param $job
     * @return array
     */
    protected function getPagesRatings(array $args, &$job): array
    {
        $out = [];

        $job = Enjoin::get('FeedbackQueue')->findOne([
            'where' => ['id' => $args['feedbackJobId']],
            'attributes' => ['id', 'first_name', 'middle_name', 'last_name', 'email'],
            'include' => [
                'model' => Enjoin::get('Pages'),
                'required' => true,
                'attributes' => ['id', 'type']
            ]
        ]);
        if ($job) {
            $theProduct = $job->page;
            foreach ($args['criteria'] as $criterion) {
                $out [] = [
                    'feedback_types_id' => $criterion['id'],
                    'text_note_val' => null,
                    'num_grade_val' => $criterion['value'],
                    'bool_grade_val' => null,
                ];
            }

            $textNote = trim($args['textNote']);
            if ($textNote) {
                $feedbackType = Enjoin::get('FeedbackTypes')->findOne([
                    'where' => [
                        'product_type' => $theProduct->type,
                        'tier' => FeedbackTypes::TEXT_NOTE_TYPE
                    ],
                    'attributes' => ['id'],
                ]);
                if ($feedbackType) {
                    $out [] = [
                        'feedback_types_id' => $feedbackType->id,
                        'text_note_val' => $textNote,
                        'num_grade_val' => null,
                        'bool_grade_val' => null,
                    ];
                }
            }

            $feedbackType = Enjoin::get('FeedbackTypes')->findOne([
                'where' => [
                    'product_type' => $theProduct->type,
                    'tier' => FeedbackTypes::BOOL_GRADE_TYPE
                ],
                'attributes' => ['id'],
            ]);
            if ($feedbackType) {
                $out [] = [
                    'feedback_types_id' => $feedbackType->id,
                    'text_note_val' => null,
                    'num_grade_val' => null,
                    'bool_grade_val' => $args['satisfaction'],
                ];
            }
        }

        return $out;
    }

    /**
     * @param array $input
     * @param \Enjoin\Record\Record $feedbackQueueJob
     * @param array $pagesRatings
     * @param int $pagesFeedbackTypesInsertId
     */
    protected function dealWithRepliesAndRatersTables(array &$input, Record $feedbackQueueJob, array &$pagesRatings, int $pagesFeedbackTypesInsertId): void
    {
        $raterId = $this->fillRatersTable($feedbackQueueJob);
        $replyId = $this->fillRepliesTable($input, $feedbackQueueJob);

        # raters_replies:
        Enjoin::get('RatersReplies')->create([
            'raters_id' => $raterId,
            'replies_id' => $replyId,
            'value' => $input['satisfaction'],
        ]);

        # replies_feedback_types:
        Enjoin::get('RepliesFeedbackTypes')->update([
            'replies_id' => $replyId
        ], [
            'where' => ['id' => range($pagesFeedbackTypesInsertId, $pagesFeedbackTypesInsertId + (count($pagesRatings) - 1))]
        ]);
    }

    /**
     * @param \Enjoin\Record\Record $feedbackQueueJob
     * @return int
     */
    protected function fillRatersTable(Record &$feedbackQueueJob): int
    {
        return Enjoin::get('Raters')->findOne([
                'where' => ['email' => $feedbackQueueJob->email],
                'attributes' => ['id']
            ])->id
            ?? Enjoin::get('Raters')->create([
                'email' => $feedbackQueueJob->email,
                'token' => (new \Bundles\Foundation\Ksb\Lib\Feedback)->getSecurityToken(),
                'ttl' => Carbon::now()->addDay()->addHour()->format('Y-m-d H:i:00'),
            ])->id;
    }

    /**
     * @param array $input
     * @param \Enjoin\Record\Record $feedbackQueueJob
     * @return int
     */
    protected function fillRepliesTable(array &$input, Record $feedbackQueueJob): int
    {
        $textNote = trim($input['textNote']);
        $satisfaction = $input['satisfaction'] ?? true;
        return Enjoin::get('Replies')->create([
            'on_state' => false,
            'pages_id' => $feedbackQueueJob->page->id,
            'name' => trim(preg_replace(
                '/ {2,}/',
                ' ',
                $feedbackQueueJob->first_name
                    . ' '
                    . $feedbackQueueJob->middle_name
                    . ' '
                    . $feedbackQueueJob->last_name
            )),
            'content' => $textNote ?: null,
            'type' => $satisfaction ? 'pos' : 'neg',
        ])->id;
    }

}
