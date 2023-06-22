<?php

namespace Models;

use Enjoin;

class FeedbackQueue extends Definition
{

    const AWAIT_STATUS = 'await';
    const SENT_STATUS = 'sent';
    const COMMITTED_STATUS = 'committed';
    const SKIPPED_STATUS = 'skipped';

    public $table = 'feedback_queue';
    public $timestamps = false;

    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'end_date' => ['type' => Enjoin::Date()],
            'status' => ['type' => Enjoin::Enum()],
            'email' => ['type' => Enjoin::String()],
            'pages_id' => ['type' => Enjoin::Integer()],
            'first_name' => ['type' => Enjoin::String()],
            'middle_name' => ['type' => Enjoin::String()],
            'last_name' => ['type' => Enjoin::String()],
            'tries_counter' => ['type' => Enjoin::Integer()],
            'token' => ['type' => Enjoin::String()],
        ];
    }

    public function getRelations()
    {
        return [Enjoin::belongsTo(Enjoin::get('Pages'), ['foreignKey' => 'pages_id'])];
    }

}
