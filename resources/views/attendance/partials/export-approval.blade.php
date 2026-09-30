<div class="approval">
    <div class="caption">Approved and verified by:</div>
    <img src="{{ $signatureDataUri }}" alt="Signature">
    <div class="name">{{ $approverName }}</div>
    @if ($showPosition)
        <div>{{ $approverPosition }}</div>
    @endif
</div>
