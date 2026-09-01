<?php

namespace App;

enum EmailReplyClassification: string
{
    case Bounce = 'bounce';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case NotNow = 'not_now';
    case DoNotContact = 'do_not_contact';
    case PossibleLead = 'possible_lead';
    case NotLead = 'not_lead';
    case NeedsReview = 'needs_review';
    case OutOfOffice = 'out_of_office';
    case AutomaticReply = 'automatic_reply';
}
