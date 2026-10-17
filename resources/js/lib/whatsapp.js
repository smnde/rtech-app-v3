// Helper link WhatsApp. Nomor lokal "08xx" dinormalisasi ke format 62.
export function waNumber(phone) {
    const digits = String(phone ?? '').replace(/\D/g, '');
    if (!digits) return '';
    return digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
}

export function waLink(phone, message = '') {
    const number = waNumber(phone);
    if (!number) return '';
    return `https://wa.me/${number}${message ? `?text=${encodeURIComponent(message)}` : ''}`;
}

export function openWhatsapp(phone, message = '') {
    const url = waLink(phone, message);
    if (!url) return false;
    window.open(url, '_blank', 'noopener');
    return true;
}
