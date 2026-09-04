import QRCode from 'qrcode';

export type AttendanceCardUser = {
    name: string;
    roleLabel: string;
    team: string | null;
};

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
 * Draw a downloadable identity card (name, role, QR code) onto a canvas
 * and return it as a PNG data URL.
 */
export async function drawIdentityCard(
    user: AttendanceCardUser,
    qrValue: string,
): Promise<string> {
    const width = 640;
    const height = 400;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('Canvas is not supported in this browser.');
    }

    const gradient = ctx.createLinearGradient(0, 0, width, 0);
    gradient.addColorStop(0, '#4338ca');
    gradient.addColorStop(1, '#7c3aed');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);

    ctx.fillStyle = '#ffffff';
    ctx.font = '600 28px Helvetica, Arial, sans-serif';
    ctx.fillText(user.name, 32, 56);

    ctx.font = '400 16px Helvetica, Arial, sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.fillText(user.roleLabel, 32, 84);

    if (user.team) {
        ctx.fillText(user.team, 32, 108);
    }

    ctx.fillStyle = 'rgba(255,255,255,0.6)';
    ctx.font = '400 12px Helvetica, Arial, sans-serif';
    ctx.fillText('LeadGen Central — Attendance ID', 32, height - 24);

    const qrDataUrl = await generateQrDataUrl(qrValue);
    const qrImage = await loadImage(qrDataUrl);
    const qrSize = 220;
    const qrX = width - qrSize - 32;
    const qrY = (height - qrSize) / 2;

    ctx.fillStyle = '#ffffff';
    roundRect(ctx, qrX - 12, qrY - 12, qrSize + 24, qrSize + 24, 12);
    ctx.fill();
    ctx.drawImage(qrImage, qrX, qrY, qrSize, qrSize);

    return canvas.toDataURL('image/png');
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
): void {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + width, y, x + width, y + height, radius);
    ctx.arcTo(x + width, y + height, x, y + height, radius);
    ctx.arcTo(x, y + height, x, y, radius);
    ctx.arcTo(x, y, x + width, y, radius);
    ctx.closePath();
}

export function downloadDataUrl(dataUrl: string, filename: string): void {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    link.click();
}
