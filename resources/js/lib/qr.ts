import QRCode from 'qrcode';

export type AttendanceCardUser = {
    id: number;
    name: string;
    roleLabel: string;
    team: string | null;
};

const CARD_WIDTH = 640;
const CARD_HEIGHT = 1000;
const CARD_GAP = 48;
const TEAL = '#0f766e';
const GREEN = '#16a34a';
const CYAN_ACCENT = '#06b6d4';
const GREEN_ACCENT = '#10b981';
const INK = '#0f172a';
const MUTED = '#64748b';

/** Zero-padded internal ID badge shown on the card, e.g. "LGC-005". */
export function formatCardUserId(id: number): string {
    return `LGC-${String(id).padStart(3, '0')}`;
}

/**
 * Render a QR code for the given value as a data URL PNG.
 */
export async function generateQrDataUrl(value: string): Promise<string> {
    return QRCode.toDataURL(value, {
        errorCorrectionLevel: 'M',
        margin: 1,
        width: 320,
    });
}

/**
 * Draw a two-sided, printable identity card (front: photo ID; back:
 * attendance QR pass) onto a canvas and return it as a PNG data URL.
 */
export async function drawIdentityCard(
    user: AttendanceCardUser,
    qrValue: string,
): Promise<string> {
    const width = CARD_WIDTH * 2 + CARD_GAP;
    const height = CARD_HEIGHT;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('Canvas is not supported in this browser.');
    }

    ctx.fillStyle = '#e2e8f0';
    ctx.fillRect(0, 0, width, height);

    const qrDataUrl = await generateQrDataUrl(qrValue);
    const qrImage = await loadImage(qrDataUrl);

    drawFrontCard(ctx, 0, 0, user);
    drawBackCard(ctx, CARD_WIDTH + CARD_GAP, 0, user, qrValue, qrImage);

    return canvas.toDataURL('image/png');
}

function headerGradient(
    ctx: CanvasRenderingContext2D,
    x: number,
    width: number,
): CanvasGradient {
    const gradient = ctx.createLinearGradient(x, 0, x + width, 0);
    gradient.addColorStop(0, TEAL);
    gradient.addColorStop(1, GREEN);

    return gradient;
}

function drawCardShell(
    ctx: CanvasRenderingContext2D,
    x: number,
    accentColorLeft: string,
    accentColorRight: string,
): void {
    roundRect(ctx, x, 0, CARD_WIDTH, CARD_HEIGHT, 24);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.08)';
    ctx.lineWidth = 1;
    ctx.stroke();

    ctx.fillStyle = accentColorLeft;
    roundRect(ctx, x + 24, 100, 6, CARD_HEIGHT - 160, 3);
    ctx.fill();

    ctx.fillStyle = accentColorRight;
    roundRect(ctx, x + CARD_WIDTH - 30, 100, 6, CARD_HEIGHT - 160, 3);
    ctx.fill();
}

function drawFrontCard(
    ctx: CanvasRenderingContext2D,
    x: number,
    _y: number,
    user: AttendanceCardUser,
): void {
    drawCardShell(ctx, x, CYAN_ACCENT, GREEN_ACCENT);

    // Header band.
    roundRect(ctx, x, 0, CARD_WIDTH, 130, 24, 'top');
    ctx.fillStyle = headerGradient(ctx, x, CARD_WIDTH);
    ctx.fill();

    // Brand mark.
    ctx.fillStyle = 'rgba(255,255,255,0.16)';
    roundRect(ctx, x + 36, 36, 44, 44, 10);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.font = '700 22px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('L', x + 58, 66);
    ctx.textAlign = 'left';

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 30px Helvetica, Arial, sans-serif';
    ctx.fillText('LeadGen Central', x + 96, 62);
    ctx.font = '400 15px Helvetica, Arial, sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.fillText('Official User Identification Card', x + 96, 90);

    // Photo slot with corner brackets.
    const photoX = x + (CARD_WIDTH - 260) / 2;
    const photoY = 168;
    const photoSize = 260;
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    roundRect(ctx, photoX, photoY, photoSize, photoSize, 4);
    ctx.stroke();
    drawCornerBrackets(ctx, photoX, photoY, photoSize, photoSize, CYAN_ACCENT);
    ctx.fillStyle = MUTED;
    ctx.textAlign = 'center';
    ctx.font = '600 20px Helvetica, Arial, sans-serif';
    ctx.fillText('1 x 1', x + CARD_WIDTH / 2, photoY + photoSize / 2 - 4);
    ctx.font = '400 14px Helvetica, Arial, sans-serif';
    ctx.fillText('PHOTO SLOT', x + CARD_WIDTH / 2, photoY + photoSize / 2 + 20);
    ctx.textAlign = 'left';

    let cursorY = photoY + photoSize + 48;

    // Underline pair.
    ctx.fillStyle = CYAN_ACCENT;
    ctx.fillRect(x + 64, cursorY, CARD_WIDTH - 128, 3);
    ctx.fillStyle = GREEN_ACCENT;
    ctx.fillRect(x + 64, cursorY, (CARD_WIDTH - 128) * 0.36, 3);
    cursorY += 34;

    ctx.fillStyle = MUTED;
    ctx.font = '600 13px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('NAME', x + CARD_WIDTH / 2, cursorY);
    cursorY += 36;

    ctx.fillStyle = INK;
    ctx.font = '700 30px Helvetica, Arial, sans-serif';
    const nameLines = wrapCenteredText(
        ctx,
        user.name,
        x + CARD_WIDTH / 2,
        cursorY,
        CARD_WIDTH - 96,
        38,
    );
    cursorY += nameLines.length * 38 + 16;

    ctx.fillStyle = TEAL;
    ctx.font = '700 19px Helvetica, Arial, sans-serif';
    ctx.fillText(capitalize(user.roleLabel), x + CARD_WIDTH / 2, cursorY);
    cursorY += 32;

    if (user.team) {
        ctx.fillStyle = MUTED;
        ctx.font = '400 16px Helvetica, Arial, sans-serif';
        ctx.fillText(user.team, x + CARD_WIDTH / 2, cursorY);
        cursorY += 32;
    }

    ctx.textAlign = 'left';

    // User ID badge.
    const badgeHeight = 74;
    const badgeY = Math.max(cursorY + 36, CARD_HEIGHT - 190);
    const badgeX = x + 64;
    const badgeWidth = CARD_WIDTH - 128;
    ctx.fillStyle = '#d1fae5';
    roundRect(ctx, badgeX, badgeY, badgeWidth, badgeHeight, 10);
    ctx.fill();
    ctx.fillStyle = MUTED;
    ctx.font = '600 12px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('USER ID', x + CARD_WIDTH / 2, badgeY + 28);
    ctx.fillStyle = INK;
    ctx.font = '700 24px Helvetica, Arial, sans-serif';
    ctx.fillText(formatCardUserId(user.id), x + CARD_WIDTH / 2, badgeY + 56);
    ctx.textAlign = 'left';

    // Signature line.
    const sigY = Math.max(badgeY + badgeHeight + 50, CARD_HEIGHT - 76);
    ctx.strokeStyle = INK;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(x + 64, sigY);
    ctx.lineTo(x + CARD_WIDTH - 64, sigY);
    ctx.stroke();
    ctx.fillStyle = MUTED;
    ctx.font = '400 14px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Signature', x + CARD_WIDTH / 2, sigY + 24);
    ctx.textAlign = 'left';

    // Bottom accent bar.
    ctx.fillStyle = headerGradient(ctx, x, CARD_WIDTH);
    roundRect(ctx, x, CARD_HEIGHT - 8, CARD_WIDTH, 8, 4, 'bottom');
    ctx.fill();
}

function drawBackCard(
    ctx: CanvasRenderingContext2D,
    x: number,
    _y: number,
    user: AttendanceCardUser,
    qrValue: string,
    qrImage: HTMLImageElement,
): void {
    drawCardShell(ctx, x, CYAN_ACCENT, GREEN_ACCENT);

    roundRect(ctx, x, 0, CARD_WIDTH, 130, 24, 'top');
    ctx.fillStyle = headerGradient(ctx, x, CARD_WIDTH);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 30px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Attendance QR', x + CARD_WIDTH / 2, 62);
    ctx.font = '400 15px Helvetica, Arial, sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.fillText('Scan to record user attendance', x + CARD_WIDTH / 2, 90);

    // Authorized pass pill.
    const pillY = 168;
    const pillWidth = CARD_WIDTH - 160;
    const pillX = x + 80;
    ctx.fillStyle = '#ecfeff';
    roundRect(ctx, pillX, pillY, pillWidth, 40, 8);
    ctx.fill();
    ctx.fillStyle = TEAL;
    ctx.font = '700 13px Helvetica, Arial, sans-serif';
    ctx.fillText('AUTHORIZED ATTENDANCE PASS', x + CARD_WIDTH / 2, pillY + 26);

    // QR box with corner brackets.
    const qrBoxY = pillY + 60;
    const qrBoxSize = CARD_WIDTH - 160;
    ctx.fillStyle = '#ffffff';
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    roundRect(ctx, pillX, qrBoxY, qrBoxSize, qrBoxSize, 8);
    ctx.fill();
    ctx.stroke();
    drawCornerBrackets(ctx, pillX, qrBoxY, qrBoxSize, qrBoxSize, GREEN_ACCENT);

    const qrPadding = 28;
    ctx.drawImage(
        qrImage,
        pillX + qrPadding,
        qrBoxY + qrPadding,
        qrBoxSize - qrPadding * 2,
        qrBoxSize - qrPadding * 2,
    );

    // User ID badge.
    const badgeY = qrBoxY + qrBoxSize + 36;
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1.5;
    roundRect(ctx, pillX, badgeY, pillWidth, 60, 10);
    ctx.stroke();
    ctx.fillStyle = INK;
    ctx.font = '700 22px Helvetica, Arial, sans-serif';
    ctx.fillText(
        `User ID: ${formatCardUserId(user.id)}`,
        x + CARD_WIDTH / 2,
        badgeY + 38,
    );

    ctx.fillStyle = MUTED;
    ctx.font = '400 12px Helvetica, Arial, sans-serif';
    ctx.fillText(
        truncateMiddle(ctx, qrValue, pillWidth - 16),
        x + CARD_WIDTH / 2,
        badgeY + 92,
    );

    // Footer.
    const footerY = CARD_HEIGHT - 48;
    ctx.fillStyle = TEAL;
    ctx.fillRect(x + CARD_WIDTH / 2 - 90, footerY - 20, 180, 3);
    ctx.font = '700 13px Helvetica, Arial, sans-serif';
    ctx.fillStyle = INK;
    ctx.fillText('LEADGEN CENTRAL', x + CARD_WIDTH / 2, footerY);
    ctx.textAlign = 'left';

    ctx.fillStyle = headerGradient(ctx, x, CARD_WIDTH);
    roundRect(ctx, x, CARD_HEIGHT - 8, CARD_WIDTH, 8, 4, 'bottom');
    ctx.fill();
}

function drawCornerBrackets(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    color: string,
): void {
    const size = 18;
    const inset = 10;
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;

    const corners: Array<[number, number, number, number]> = [
        [x - inset, y - inset, 1, 1],
        [x + width + inset, y - inset, -1, 1],
        [x - inset, y + height + inset, 1, -1],
        [x + width + inset, y + height + inset, -1, -1],
    ];

    corners.forEach(([cx, cy, dx, dy]) => {
        ctx.beginPath();
        ctx.moveTo(cx, cy + size * dy);
        ctx.lineTo(cx, cy);
        ctx.lineTo(cx + size * dx, cy);
        ctx.stroke();
    });
}

function wrapCenteredText(
    ctx: CanvasRenderingContext2D,
    text: string,
    centerX: number,
    startY: number,
    maxWidth: number,
    lineHeight: number,
): string[] {
    const words = text.split(' ');
    const lines: string[] = [];
    let current = '';

    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;

        if (ctx.measureText(candidate).width > maxWidth && current) {
            lines.push(current);
            current = word;
        } else {
            current = candidate;
        }
    }

    if (current) {
        lines.push(current);
    }

    const originalAlign = ctx.textAlign;
    ctx.textAlign = 'center';
    lines.forEach((line, index) => {
        ctx.fillText(line, centerX, startY + index * lineHeight);
    });
    ctx.textAlign = originalAlign;

    return lines;
}

function capitalize(value: string): string {
    return value.replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function truncateMiddle(
    ctx: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
): string {
    if (ctx.measureText(text).width <= maxWidth) {
        return text;
    }

    const ellipsis = '…';
    let left = Math.ceil(text.length / 2);
    let right = text.length - left;

    while (left > 0 && right > 0) {
        const candidate = text.slice(0, left) + ellipsis + text.slice(text.length - right);

        if (ctx.measureText(candidate).width <= maxWidth) {
            return candidate;
        }

        left -= 1;
        right -= 1;
    }

    return ellipsis;
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = src;
    });
}

function roundRect(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
    only?: 'top' | 'bottom',
): void {
    const topRadius = only === 'bottom' ? 0 : radius;
    const bottomRadius = only === 'top' ? 0 : radius;

    ctx.beginPath();
    ctx.moveTo(x + topRadius, y);
    ctx.arcTo(x + width, y, x + width, y + height, topRadius);
    ctx.arcTo(x + width, y + height, x, y + height, bottomRadius);
    ctx.arcTo(x, y + height, x, y, bottomRadius);
    ctx.arcTo(x, y, x + width, y, topRadius);
    ctx.closePath();
}

export function downloadDataUrl(dataUrl: string, filename: string): void {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    link.click();
}
