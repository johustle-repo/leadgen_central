<?php

namespace App;

enum LeadSource: string
{
    case Csv = 'csv';
    case Manual = 'manual';
    case Scraper = 'scraper';
}
