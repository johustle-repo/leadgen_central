<?php

namespace App;

enum LeadStatus: string
{
    case Raw = 'raw';
    case Valid = 'valid';
    case ValidationError = 'validation_error';
    case Validated = 'validated';
    case Enriched = 'enriched';
    case NeedsReview = 'needs_review';
    case PossibleLead = 'possible_lead';
    case QualifiedLead = 'qualified_lead';
    case NotLead = 'not_a_lead';
    case Forwarded = 'forwarded';
    case Duplicate = 'duplicate';
}
