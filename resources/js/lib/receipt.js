// Nota/invoice. Lebar kertas = A4 (210mm), tinggi mengikuti panjang isi.
// Kunci: @page size harus <length> <length> — kalau pakai "auto", browser
// fallback ke A4 penuh dan nota cuma nangkring di pojok. Jadi tinggi diukur dulu.

const DEFAULT_WIDTH_MM = 210; // lebar A4, portrait
const MM_PER_PX = 25.4 / 96;

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function num(v) {
    return new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function money(v) {
    return `Rp ${num(v)}`;
}

function formatDate(d) {
    if (!d) return '';
    return new Date(String(d).replace(' ', 'T')).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function formatSpec(spec) {
    if (!spec || typeof spec !== 'object') return '';
    return Object.entries(spec)
        .filter(([, v]) => v !== null && v !== undefined && `${v}`.trim() !== '')
        .map(([k, v]) => `${k.charAt(0).toUpperCase()}${k.slice(1)}: ${v}`)
        .join(' · ');
}

function pair(label, value) {
    if (value === null || value === undefined || value === '') return '';
    return `<div class="pair"><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`;
}

const PAPER_CSS = `
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        width: ${DEFAULT_WIDTH_MM}mm; padding: 12mm 14mm;
        font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 11px; line-height: 1.5; color: #111827; background: #fff;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16mm; padding-bottom: 5mm; border-bottom: 2px solid #111827; }
    .store-name { font-size: 19px; font-weight: 700; letter-spacing: .3px; }
    .store-line { font-size: 10px; color: #4b5563; margin-top: 2px; }
    .doc-title { text-align: right; flex-shrink: 0; }
    .doc-title h1 { font-size: 15px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #374151; }
    .invoice-no { font-family: ui-monospace, 'Courier New', monospace; font-size: 14px; font-weight: 700; margin-top: 3px; }
    .badge { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px; font-size: 9px; font-weight: 700; letter-spacing: .8px; }
    .badge-paid { background: #d1fae5; color: #065f46; }
    .badge-due { background: #fef3c7; color: #92400e; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12mm; padding: 5mm 0 3mm; }
    .meta .pair { display: flex; justify-content: space-between; gap: 6mm; padding: 1.3mm 0; border-bottom: 1px dotted #e5e7eb; font-size: 10.5px; }
    .meta dt { color: #6b7280; flex-shrink: 0; }
    .meta dd { font-weight: 500; text-align: right; word-break: break-word; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 3mm; }
    table.items th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; padding: 2.5mm 2mm; text-align: left; border-top: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; }
    table.items td { padding: 2.5mm 2mm; border-bottom: 1px solid #eef0f3; vertical-align: top; font-size: 10.5px; }
    table.items .num { text-align: right; white-space: nowrap; }
    table.items .idx { width: 8mm; color: #9ca3af; }
    .item-name { font-weight: 600; }
    .item-sub { font-size: 9px; color: #6b7280; margin-top: 1px; }
    .item-spec { font-size: 8.5px; color: #9ca3af; margin-top: 1px; }
    .totals { width: 78mm; margin-left: auto; margin-top: 5mm; font-size: 10.5px; }
    .totals .row { display: flex; justify-content: space-between; padding: 1.3mm 0; }
    .totals .row span:last-child { white-space: nowrap; }
    .totals .grand { border-top: 1.5px solid #111827; margin-top: 2mm; padding-top: 3mm; font-size: 15px; font-weight: 700; }
    .notes { margin-top: 6mm; padding: 3mm 4mm; background: #f9fafb; border-radius: 2mm; font-size: 10px; color: #374151; white-space: pre-line; }
    .foot { margin-top: 9mm; padding-top: 4mm; border-top: 1px dashed #d1d5db; text-align: center; font-size: 10px; color: #6b7280; }
    .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; margin-top: 5mm; }
`;

// Kepala dokumen: identitas toko + judul & nomor nota.
export function storeHead({ store = {}, title = '', invoice = '', badge = null }) {
    const logoHtml = `<img src="/icons/icon-192.png" alt="logo"
        style="width:40px;height:40px;object-fit:contain;border-radius:8px;margin-right:8px;flex-shrink:0;" />`;

    return `<div class="doc-head">
        <div style="display:flex;align-items:center;">
            ${logoHtml}
            <div>
                <div class="store-name">${esc(store.name ?? 'Toko')}</div>
                ${store.address ? `<div class="store-line">${esc(store.address)}</div>` : ''}
                ${store.phone ? `<div class="store-line">${esc(store.phone)}</div>` : ''}
            </div>
        </div>
        <div class="doc-title">
            <h1>${esc(title)}</h1>
            <div class="invoice-no">${esc(invoice)}</div>
            ${badge ? `<div class="badge ${badge.cls}">${esc(badge.label)}</div>` : ''}
        </div>
    </div>`;
}

export function storeFoot(store = {}) {
    return `<div class="foot">
        <div>${esc(store.footer ?? 'Terima kasih')}</div>
        <div style="margin-top:4px;font-size:8px;color:#9ca3af;line-height:1.5;">
            Garansi berlaku selama tidak ada indikasi Human Error / Kesalahan Pengguna.<br>
            Tidak ada pengembalian barang / uang untuk barang yang sudah dibeli.
        </div>
    </div>`;
}

// Baris tabel barang: nama, kode, serial, spesifikasi, qty, harga, diskon, subtotal.
function itemRows(items = [], { showDiscount = false } = {}) {
    return items.map((li, i) => {
        const qty = li.quantity ?? li.qty ?? 0;
        const price = li.price ?? li.buy_price ?? 0;
        const subtotal = li.subtotal ?? (qty * price);
        const spec = formatSpec(li.specification);

        const sub = [
            li.item_code ? `<span>${esc(li.item_code)}</span>` : '',
            li.serial_number ? `<span>SN: ${esc(li.serial_number)}</span>` : '',
        ].filter(Boolean).join(' &nbsp;·&nbsp; ');

        return `<tr>
            <td class="idx">${i + 1}</td>
            <td>
                <div class="item-name">${esc(li.item_name ?? li.name ?? '')}</div>
                ${sub ? `<div class="item-sub">${sub}</div>` : ''}
                ${spec ? `<div class="item-spec">${esc(spec)}</div>` : ''}
            </td>
            <td class="num">${qty}</td>
            <td class="num">${num(price)}</td>
            ${showDiscount ? `<td class="num">${Number(li.discount) > 0 ? `&minus; ${num(li.discount)}` : '—'}</td>` : ''}
            <td class="num">${num(subtotal)}</td>
        </tr>`;
    }).join('');
}

function itemTable(items, { showDiscount }) {
    return `<table class="items">
        <thead><tr>
            <th class="idx">#</th>
            <th>Barang</th>
            <th class="num">Qty</th>
            <th class="num">Harga</th>
            ${showDiscount ? '<th class="num">Diskon</th>' : ''}
            <th class="num">Subtotal</th>
        </tr></thead>
        <tbody>${itemRows(items, { showDiscount })}</tbody>
    </table>`;
}

// Isi nota penjualan/pembelian.
export function saleBody({ store = {}, sale, title = 'NOTA PENJUALAN' }) {
    if (!sale) return '';

    const items = sale.sale_items || sale.items || [];
    const isPaid = sale.payment_status ? sale.payment_status === 'lunas' : true;
    const isPurchase = sale.supplier !== undefined;
    const itemDiscount = items.reduce((sum, li) => sum + (Number(li.discount) || 0), 0);
    const hasDiscount = Number(sale.discount) > 0;
    const headerDiscount = Number(sale.header_discount) || 0;

    const meta = [
        pair('Tanggal', formatDate(sale.created_at)),
        pair('Kasir', sale.user?.name ?? sale.cashier),
        pair(isPurchase ? 'Supplier' : 'Customer', sale.customer?.name ?? sale.supplier ?? 'Umum'),
        pair('Telepon', sale.customer?.phone ?? sale.supplier_phone),
        pair('Pembayaran', sale.payment_method),
        pair('No. Nota Supplier', sale.buy_from_reference),
        pair('Dibayar', sale.paid_at ? formatDate(sale.paid_at) : null),
        pair('DP', sale.dp_amount ? money(sale.dp_amount) : null),
    ].join('');

    return `
        ${storeHead({
            store,
            title,
            invoice: sale.invoice_no ?? sale.reference_no ?? '',
            badge: sale.payment_status
                ? { label: isPaid ? 'LUNAS' : 'BELUM LUNAS', cls: isPaid ? 'badge-paid' : 'badge-due' }
                : null,
        })}

        <div class="meta">${meta}</div>

        ${itemTable(items, { showDiscount: true })}

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>${money(sale.subtotal)}</span></div>
            ${itemDiscount > 0 ? `<div class="row"><span>Diskon item</span><span>&minus; ${money(itemDiscount)}</span></div>` : ''}
            ${hasDiscount ? `<div class="row"><span>Diskon</span><span>&minus; ${money(sale.discount)}</span></div>` : ''}
            ${headerDiscount > 0 ? `<div class="row"><span>Diskon khusus</span><span>&minus; ${money(headerDiscount)}</span></div>` : ''}
            <div class="row grand"><span>TOTAL</span><span>${money(sale.total)}</span></div>
        </div>

        ${sale.notes ? `<div class="notes">Catatan: ${esc(sale.notes)}</div>` : ''}
        ${storeFoot(store)}`;
}

/**
 * Buka window nota, ukur tinggi isi, set kertas, lalu print.
 * @param {object} opts
 * @param {string} opts.title     judul window / nama file PDF
 * @param {string} opts.body      HTML isi nota
 * @param {number} [opts.widthMm=210]  lebar kertas (A4 = 210)
 */
export function printReceiptHtml({ title = 'Nota', body = '', widthMm = DEFAULT_WIDTH_MM }) {
    const w = window.open('', '_blank', 'width=900,height=760');
    if (!w) return;

    w.document.write(`<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">
        <title>${esc(title)}</title>
        <style id="paper-size">@page { size: ${widthMm}mm 297mm; margin: 0; }</style>
        <style>${PAPER_CSS}</style>
        </head><body>${body}</body></html>`);
    w.document.close();
    w.focus();

    // Jeda agar layout siap, lalu tinggi isi dipakai sebagai tinggi kertas.
    setTimeout(() => {
        const heightMm = Math.max(80, Math.ceil(w.document.body.scrollHeight * MM_PER_PX) + 2);
        const sizeEl = w.document.getElementById('paper-size');
        if (sizeEl) sizeEl.textContent = `@page { size: ${widthMm}mm ${heightMm}mm; margin: 0; }`;
        w.print();
    }, 350);
}

/**
 * @param {object}  opts
 * @param {object}  opts.store  { name, address, phone, footer }
 * @param {object}  opts.sale   objek penjualan/pembelian
 * @param {string}  [opts.title='NOTA PENJUALAN']
 */
export function printReceipt({ store = {}, sale, title = 'NOTA PENJUALAN' }) {
    if (!sale) return;
    printReceiptHtml({
        title: sale.invoice_no ?? sale.reference_no ?? 'Nota',
        body: saleBody({ store, sale, title }),
    });
}

// Pasang dokumen di luar layar, siap dijepret html2canvas.
function mountOffscreen(body, widthMm) {
    const holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-99999px;top:0;background:#fff;';
    const style = document.createElement('style');
    style.textContent = PAPER_CSS;
    const doc = document.createElement('div');
    doc.style.width = `${widthMm}mm`;
    doc.innerHTML = body;
    holder.append(style, doc);
    document.body.appendChild(holder);
    // lastElementChild = div dokumen (bukan <style>).
    return { holder, target: doc };
}

/**
 * Render nota jadi PDF (lebar A4, tinggi ikut isi).
 * @returns {Promise<{ blob: Blob, filename: string }>}
 */
export async function buildReceiptPdf({ title = 'Nota', body = '', widthMm = DEFAULT_WIDTH_MM, filename } = {}) {
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import('html2canvas'),
        import('jspdf'),
    ]);

    const { holder, target } = mountOffscreen(body, widthMm);
    try {
        await document.fonts?.ready;
        const canvas = await html2canvas(target, {
            backgroundColor: '#ffffff', scale: 2, logging: false,
        });

        const heightMm = (canvas.height / canvas.width) * widthMm;
        const pdf = new jsPDF({ unit: 'mm', format: [widthMm, heightMm], orientation: 'portrait' });
        pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, widthMm, heightMm);

        return {
            blob: pdf.output('blob'),
            filename: filename ?? `${String(title).toLowerCase().replace(/\s+/g, '-')}.pdf`,
        };
    } finally {
        holder.remove();
    }
}

export function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * Bagikan PDF ke aplikasi lain (WhatsApp) via Web Share API.
 * @returns {Promise<boolean>} true bila benar-benar dibagikan; false bila tak didukung.
 */
export async function shareBlob(blob, filename, text = '') {
    const file = new File([blob], filename, { type: 'application/pdf' });
    if (navigator.canShare?.({ files: [file] })) {
        await navigator.share({ files: [file], text });
        return true;
    }
    return false;
}

