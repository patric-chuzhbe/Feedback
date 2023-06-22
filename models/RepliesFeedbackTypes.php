<?php

namespace Models;

use Enjoin;

class RepliesFeedbackTypes extends Definition
{

    public $table = 'replies_feedback_types';

    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'feedback_types_id' => ['type' => Enjoin::Integer()],
            'text_note_val' => ['type' => Enjoin::Text()],
            'num_grade_val' => ['type' => Enjoin::Float()],
            'bool_grade_val' => ['type' => Enjoin::Boolean()],
            'replies_id' => ['type' => Enjoin::Integer()],
        ];
    }

    public function getRelations()
    {
        return [
            Enjoin::belongsTo(Enjoin::get('FeedbackTypes'), ['foreignKey' => 'feedback_types_id']),
            Enjoin::belongsTo(Enjoin::get('Replies'), ['foreignKey' => 'replies_id']),
        ];
    }

}
