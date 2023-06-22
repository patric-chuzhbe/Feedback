<?php

namespace Models;

use Enjoin;

class FeedbackTypes extends Definition
{

    const TEXT_NOTE_TYPE = 'text_note';
    const NUM_GRADE_TYPE = 'num_grade';
    const BOOL_GRADE_TYPE = 'bool_grade';

    public $table = 'feedback_types';
    public $timestamps = false;

    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'name' => ['type' => Enjoin::String()],
            'product_type' => ['type' => Enjoin::Enum()],
            'tier' => ['type' => Enjoin::Enum()],
            'weight' => ['type' => Enjoin::Integer()],
        ];
    }

    public function getRelations()
    {
        return [
            Enjoin::hasMany(Enjoin::get('RepliesFeedbackTypes'), ['foreignKey' => 'feedback_types_id'])
        ];
    }

}
