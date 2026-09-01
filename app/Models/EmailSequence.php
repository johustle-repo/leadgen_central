<?php

namespace App\Models;

use Database\Factories\EmailSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property array<int, array{day: int, subject: string, body: string, attach_brochure: bool}> $steps
 * @property bool $is_active
 */
class EmailSequence extends Model
{
    /** @use HasFactory<EmailSequenceFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'steps', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['steps' => 'array', 'is_active' => 'boolean'];
    }

    /** @return array<int, array{day: int, subject: string, body: string, attach_brochure: bool}> */
    public static function defaultSteps(): array
    {
        return [
            ['day' => 1, 'subject' => 'DUSCAFF Scaffolding - Global Quality, Competitive Pricing', 'body' => "Hi {{firstName}},\n\nI can help you improve your cost efficiency in scaffolding materials by offering competitive pricing without compromising on quality and standards.\n\nDUSCAFF is a global scaffolding manufacturer supplying Ringlock, Tube & Fitting, Frame, Kwikstage, Cuplock, Aluminium Towers and Ladders.\n\nPlease find our brochure attached. Would it be useful if I sent pricing for the products you currently source?\n\nRegards,", 'attach_brochure' => true],
            ['day' => 3, 'subject' => 'Potential cost savings on scaffolding materials', 'body' => "Hi {{firstName}},\n\nI just wanted to follow up on my previous email. We support scaffolding buyers worldwide with competitive pricing and products manufactured to American, British, and European standards.\n\nWould you be open to comparing our pricing for your next requirement?\n\nRegards,", 'attach_brochure' => false],
            ['day' => 7, 'subject' => 'Worth comparing scaffolding prices?', 'body' => "Hi {{firstName}},\n\nI hope you're doing well. This is my final follow-up regarding DUSCAFF scaffolding products.\n\nIf reducing material costs is currently a priority for {{companyName}}, I would be happy to prepare a quotation for comparison.\n\nRegards,", 'attach_brochure' => false],
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EmailSequenceEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(EmailSequenceEnrollment::class);
    }
}
