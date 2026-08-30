<?php

namespace App;

enum EmailReplyClassification: string
{
    case PossibleLead = 'possible_lead';
    case NotLead = 'not_lead';
    case NeedsReview = 'needs_review';
    case AutomaticReply = 'automatic_reply';
}
