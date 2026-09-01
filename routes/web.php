<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\VerifyEmailController as AuthVerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateReviewController;
use App\Http\Controllers\EmailReplyController;
use App\Http\Controllers\EmailSequenceController;
use App\Http\Controllers\GmailConnectionController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadForwardingController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\SendLeadEmailController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UploadBatchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('email/verify/{id}/{hash}', AuthVerifyEmailController::class)
    ->whereNumber('id')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'auth.session', 'verified', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('analytics', AnalyticsController::class)->name('analytics.index');
    Route::get('leads/raw.csv', [LeadController::class, 'downloadRaw'])->middleware('throttle:data-exports')->name('leads.download-raw');
    Route::get('leads/cleaned.csv', [LeadController::class, 'downloadCleaned'])->middleware('throttle:data-exports')->name('leads.download-cleaned');
    Route::delete('leads/bulk', [LeadController::class, 'bulkDestroy'])->name('leads.bulk-destroy');
    Route::resource('leads', LeadController::class);
    Route::get('uploads', [UploadBatchController::class, 'index'])->name('uploads.index');
    Route::get('uploads/create', [UploadBatchController::class, 'create'])->name('uploads.create');
    Route::post('uploads', [UploadBatchController::class, 'store'])->middleware('throttle:data-imports')->name('uploads.store');
    Route::delete('uploads/bulk', [UploadBatchController::class, 'bulkDestroy'])->name('uploads.bulk-destroy');
    Route::get('uploads/{uploadBatch}/mapping', [UploadBatchController::class, 'mapping'])->name('uploads.mapping');
    Route::post('uploads/{uploadBatch}/process', [UploadBatchController::class, 'process'])->name('uploads.process');
    Route::post('uploads/{uploadBatch}/reanalyze', [UploadBatchController::class, 'reanalyze'])->name('uploads.reanalyze');
    Route::delete('uploads/{uploadBatch}', [UploadBatchController::class, 'destroy'])->name('uploads.destroy');
    Route::get('uploads/{uploadBatch}', [UploadBatchController::class, 'show'])->name('uploads.show');
    Route::get('uploads/{uploadBatch}/errors.csv', [UploadBatchController::class, 'errors'])->middleware('throttle:data-exports')->name('uploads.errors');
    Route::get('uploads/{uploadBatch}/cleaned.csv', [UploadBatchController::class, 'cleaned'])->middleware('throttle:data-exports')->name('uploads.cleaned');
    Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::get('verification/{lead}', [VerificationController::class, 'show'])->name('verification.show');
    Route::put('verification/{lead}', [VerificationController::class, 'update'])->name('verification.update');
    Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');
    Route::post('leads/{lead}/send-email', SendLeadEmailController::class)->middleware('throttle:20,1')->name('leads.send-email');
    Route::post('leads/{lead}/forwardings', [LeadForwardingController::class, 'store'])->name('leads.forwardings.store');
    Route::get('duplicates', [DuplicateReviewController::class, 'index'])->name('duplicates.index');
    Route::put('duplicates/{duplicateMatch}', [DuplicateReviewController::class, 'update'])->name('duplicates.update');
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/email-sequence', [EmailSequenceController::class, 'toggleForUser'])->name('users.email-sequence.toggle');
    Route::get('system-settings', [SystemSettingController::class, 'edit'])->name('system-settings.edit');
    Route::put('system-settings', [SystemSettingController::class, 'update'])->middleware(['can:manage-settings', 'password.confirm'])->name('system-settings.update');
    Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
    Route::get('email-replies', [EmailReplyController::class, 'index'])->name('email-replies.index');
    Route::put('email-replies/read-all', [EmailReplyController::class, 'markAllRead'])->name('email-replies.mark-all-read');
    Route::put('email-replies/{emailReply}', [EmailReplyController::class, 'update'])->name('email-replies.update');
    Route::get('email-sequences', [EmailSequenceController::class, 'index'])->name('email-sequences.index');
    Route::put('email-sequences', [EmailSequenceController::class, 'update'])->name('email-sequences.update');
    Route::post('email-sequences/enroll', [EmailSequenceController::class, 'enroll'])->middleware('throttle:30,1')->name('email-sequences.enroll');
    Route::delete('email-sequences/enrollments/{enrollment}', [EmailSequenceController::class, 'cancel'])->name('email-sequences.cancel');
    Route::post('integrations/gmail/connect', [GmailConnectionController::class, 'connect'])->middleware('throttle:integrations')->name('gmail.connect');
    Route::get('integrations/gmail/callback', [GmailConnectionController::class, 'callback'])->name('gmail.callback');
    Route::post('integrations/gmail/sync', [GmailConnectionController::class, 'sync'])->middleware('throttle:integrations')->name('gmail.sync');
    Route::delete('integrations/gmail/disconnect', [GmailConnectionController::class, 'disconnect'])->middleware(['password.confirm', 'throttle:integrations'])->name('gmail.disconnect');
});

require __DIR__.'/settings.php';
