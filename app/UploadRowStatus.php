<?php

namespace App;

enum UploadRowStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Error = 'error';
    case Duplicate = 'duplicate';
    case NeedsReview = 'needs_review';
}
