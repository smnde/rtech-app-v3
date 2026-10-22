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

// Spek jadi 2 baris tanpa label:
//   1) CPU + GPU   2) Memory + Storage + Display (+ key lain).
function specLines(spec) {
    if (!spec || typeof spec !== 'object') return [];

    const value = (key) => {
        const v = spec[key];

        return v !== null && v !== undefined && `${v}`.trim() !== '' ? `${v}`.trim() : '';
    };

    const known = ['cpu', 'gpu', 'memory', 'storage', 'display'];
    const extra = Object.entries(spec)
        .filter(([key, v]) => !known.includes(key) && v !== null && v !== undefined && `${v}`.trim() !== '')
        .map(([, v]) => `${v}`.trim())
        .join(' · ');

    const line1 = ['cpu', 'gpu'].map(value).filter(Boolean).join(' · ');
    const line2 = [...['memory', 'storage', 'display'].map(value), extra].filter(Boolean).join(' · ');

    return [line1, line2].filter(Boolean);
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
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12mm; padding: 5mm 0 3mm; align-items: start; }
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

// ===================== PDF vector (tanpa canvas) =====================
// Nota digambar langsung ke jsPDF: tidak ada html2canvas, jadi tidak bisa
// terpotong di layar sempit dan teks tetap tajam.

const PDF = {
    width: DEFAULT_WIDTH_MM,
    height: 297,
    padX: 14,
    padY: 12,
    font: 'helvetica',
    left: 0,
    right: 0,
    widthInner: 0,
    logo: undefined,
};

// Batas aman satu halaman saat kosong (dipakai bila nota kepanjangan).
const PAGE_MM = 297;

function initPdfLayout() {
    PDF.left = PDF.padX;
    PDF.right = PDF.width - PDF.padX;
    PDF.widthInner = PDF.right - PDF.left;
}

/** Pecah teks jadi beberapa baris sesuai lebar maksimum. */
function wrapText(pdf, text, maxWidth) {
    const words = String(text ?? '').split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';
    for (const word of words) {
        const next = line ? `${line} ${word}` : word;
        if (pdf.getTextWidth(next) <= maxWidth || !line) {
            line = next;
        } else {
            lines.push(line);
            line = word;
        }
    }
    if (line) lines.push(line);
    return lines.length ? lines : [''];
}

function setFont(pdf, size, style = 'normal', color = [17, 24, 39]) {
    pdf.setFont(PDF.font, style);
    pdf.setFontSize(size);
    pdf.setTextColor(...color);
}



function drawHead(pdf, { store = {}, title = '', invoice = '', badge = null }) {
    const top = PDF.padY;
    let y = top;

    // Logo toko (PNG asli dari public/icons, bukan huruf R tiruan).
    const logo = 10;
    if (PDF.logo) {
        pdf.addImage(PDF.logo, 'PNG', PDF.left, top, logo, logo);
    }
    const textLeft = PDF.left + logo + 3;

    setFont(pdf, 14, 'bold');
    pdf.text(store.name ?? 'Toko', textLeft, top + 4.2);
    y = top + 4.2;
    setFont(pdf, 7.5, 'normal', [107, 114, 128]);
    for (const line of [store.address, store.phone].filter(Boolean)) {
        y += 3.4;
        pdf.text(String(line), textLeft, y);
    }

    // Judul + nomor nota di kanan.
    setFont(pdf, 11, 'bold', [75, 85, 99]);
    pdf.text(String(title).toUpperCase(), PDF.right, top + 4, { align: 'right' });
    setFont(pdf, 10, 'bold', [17, 24, 39]);
    pdf.text(String(invoice ?? ''), PDF.right, top + 9.5, { align: 'right' });
    if (badge) {
        const label = String(badge.label);
        setFont(pdf, 6.5, 'bold', badge.cls === 'badge-paid' ? [6, 95, 70] : [146, 64, 14]);
        const tw = pdf.getTextWidth(label) + 5;
        const bx = PDF.right - tw;
        const by = top + 12;
        pdf.setFillColor(...(badge.cls === 'badge-paid' ? [209, 250, 229] : [254, 243, 199]));
        pdf.roundedRect(bx, by, tw, 4.4, 2.2, 2.2, 'F');
        pdf.text(label, bx + tw / 2, by + 3.1, { align: 'center' });
    }

    y = Math.max(y, top + 14) + 3;
    pdf.setDrawColor(17, 24, 39);
    pdf.setLineWidth(0.6);
    pdf.line(PDF.left, y, PDF.right, y);
    return y + 5;
}

/** Dua kolom pasangan label–nilai (kembali ke bawah setelah isi kolom selesai). */
/**
 * Baris meta dua kolom.
 * @param {Array<Array<[string, string]>>|Array<[string, string]>} columns
 *   Array dua kolom (masing-masing array pair) → tiap kolom vertikal.
 *   Array pair biasa → dibagi dua kolom berselang-seling (perilaku lama).
 */
function drawPairs(pdf, columns, y) {
    const gap = 8;
    const colW = (PDF.widthInner - gap) / 2;

    const grouped = Array.isArray(columns?.[0]?.[0])
        ? columns
        : [columns.filter((_, i) => i % 2 === 0), columns.filter((_, i) => i % 2 === 1)];

    let maxY = y;

    grouped.forEach((pairs, col) => {
        const x = PDF.left + col * (colW + gap);
        let ry = y;

        pairs.forEach((row) => {
            if (!row) return;
            setFont(pdf, 7.5, 'normal', [107, 114, 128]);
            pdf.text(row[0], x, ry);
            setFont(pdf, 7.5, 'normal', [17, 24, 39]);
            const value = pdf.splitTextToSize(String(row[1]), colW * 0.6)[0] ?? '';
            pdf.text(value, x + colW, ry, { align: 'right' });
            pdf.setDrawColor(229, 231, 235);
            pdf.setLineWidth(0.2);
            pdf.setLineDashPattern([0.6, 0.8], 0);
            pdf.line(x, ry + 1.5, x + colW, ry + 1.5);
            maxY = Math.max(maxY, ry + 3);
            ry += 5.2;
        });
    });

    return maxY + 1;
}

/**
 * Tabel item nota.
 * @param {object[]} columns [{ label, width, align, render(item, i) }]
 */
function drawTable(pdf, { columns, rows, y, headerBg = [243, 244, 246] }) {
    const rowPadY = 2;
    const rowPadX = 1.8;
    const headerH = 6.5;

    // Header
    pdf.setFillColor(...headerBg);
    pdf.rect(PDF.left, y, PDF.widthInner, headerH, 'F');
    setFont(pdf, 6.5, 'bold', [107, 114, 128]);
    let x = PDF.left;
    for (const col of columns) {
        const tx = col.align === 'right' ? x + col.width - rowPadX : x + rowPadX;
        pdf.text(String(col.label).toUpperCase(), tx, y + 4.3, { align: col.align ?? 'left' });
        x += col.width;
    }
    y += headerH;
    pdf.setDrawColor(209, 213, 219);
    pdf.setLineWidth(0.25);
    pdf.line(PDF.left, y, PDF.right, y);

    rows.forEach((row, i) => {
        const cells = columns.map((col) => {
            const raw = col.render ? col.render(row, i) : row[col.key];
            const w = col.width - rowPadX * 2;

            // Sel bisa berupa string, atau array baris (tiap baris = baris baru).
            const parts = Array.isArray(raw) ? raw : [raw];
            const lines = [];
            for (const part of parts) {
                const isObj = part !== null && typeof part === 'object';
                const text = isObj ? part.text : part;
                const opts = isObj ? part : {};
                const wrapped = col.wrap === false ? [String(text ?? '')] : wrapText(pdf, text, w);
                for (const line of wrapped) {
                    lines.push({ text: line, ...opts });
                }
            }

            return { col, lines };
        });
        const rowH = Math.max(...cells.map((c) => c.lines.length)) * 4 + rowPadY * 2;

        if (y + rowH > PAGE_MM - PDF.padY) {
            pdf.addPage([PDF.width, PAGE_MM], 'landscape');
            y = PDF.padY;
        }

        x = PDF.left;
        for (const { col, lines } of cells) {
            const tx = col.align === 'right' ? x + col.width - rowPadX : x + rowPadX;
            lines.forEach((line, li) => {
                setFont(pdf, line.size ?? col.size ?? 7.5, line.style ?? col.style ?? 'normal', line.color ?? col.color ?? [17, 24, 39]);
                pdf.text(line.text, tx, y + rowPadY + 3 + li * 4, { align: col.align ?? 'left' });
            });
            x += col.width;
        }

        y += rowH;
        pdf.setDrawColor(238, 240, 243);
        pdf.setLineWidth(0.2);
        pdf.line(PDF.left, y, PDF.right, y);
    });

    return y;
}

function drawTotals(pdf, rows, y) {
    const w = 78;
    const x = PDF.right - w;
    y += 5;
    rows.forEach(([label, value, isGrand]) => {
        if (isGrand) {
            pdf.setDrawColor(17, 24, 39);
            pdf.setLineWidth(0.4);
            pdf.line(x, y - 1.5, PDF.right, y - 1.5);
            setFont(pdf, 10, 'bold', [17, 24, 39]);
            pdf.text(label, x, y + 4);
            pdf.text(String(value), PDF.right, y + 4, { align: 'right' });
            y += 7;
            return;
        }
        setFont(pdf, 7.5, 'normal', [55, 65, 81]);
        pdf.text(label, x, y + 2.5);
        pdf.text(String(value), PDF.right, y + 2.5, { align: 'right' });
        y += 5;
    });
    return y;
}

function drawNotes(pdf, text, y) {
    if (!text) return y;
    const lines = wrapText(pdf, text, PDF.widthInner - 6);
    const h = lines.length * 3.8 + 4;
    pdf.setFillColor(249, 250, 251);
    pdf.roundedRect(PDF.left, y + 2, PDF.widthInner, h, 1.5, 1.5, 'F');
    setFont(pdf, 7.2, 'normal', [55, 65, 81]);
    lines.forEach((line, i) => pdf.text(line, PDF.left + 3, y + 2 + 4 + i * 3.8));
    return y + h + 2;
}

function drawFoot(pdf, store, y, { bullets = null, bullet = false, numbered = false, withNotes = true } = {}) {
    y += 8;
    if (y > PDF.height - PDF.padY - 5) {
        pdf.addPage([PDF.width, PAGE_MM], 'landscape');
        y = PDF.padY + 8;
    }
    pdf.setDrawColor(209, 213, 219);
    pdf.setLineWidth(0.2);
    pdf.setLineDashPattern([1, 1], 0);
    pdf.line(PDF.left, y, PDF.right, y);
    pdf.setLineDashPattern([], 0);

    y += 5;
    setFont(pdf, 7.5, 'normal', [107, 114, 128]);
    const footerLines = wrapText(pdf, store.footer ?? 'Terima kasih', PDF.widthInner);
    footerLines.forEach((line) => {
        pdf.text(line, PDF.width / 2, y, { align: 'center' });
        y += 3.8;
    });

    if (!withNotes) return y;

    const notes = bullets ?? SALE_FOOTER_NOTES;
    setFont(pdf, 6, 'normal', [156, 163, 175]);
    if (bullet || numbered) {
        // Butir ringkas: rata kiri dengan tanda • / nomor; sub-poin menjorok.
        const rows = numbered
            ? flattenNotes(notes)
            : notes.map((line) => ({ text: typeof line === 'string' ? line : line.text, sub: false }));

        rows.forEach((row) => {
            const indent = row.sub ? 6 : 0;
            for (const line of wrapText(pdf, row.text, PDF.widthInner - 4 - indent)) {
                pdf.text(line, PDF.left + indent, y + 1.5);
                y += 3;
            }
        });
    } else {
        notes.forEach((line) => {
            for (const row of wrapText(pdf, line, PDF.widthInner)) {
                pdf.text(row, PDF.width / 2, y + 1.5, { align: 'center' });
                y += 3;
            }
        });
    }
    return y;
}

/**
 * Susun dokumen: tinggi kertas = tinggi isi (bukan A4 penuh), jadi di layar HP
 * nota langsung muat tanpa geser/terpotong.
 * jsPDF tidak bisa mengubah ukuran halaman setelah digambar, jadi render
 * pertama hanya mengukur, lalu render ulang dengan format [lebar, tinggi isi].
 * Nota kepanjangan dipotong ke beberapa halaman yang tingginya sama.
 */
async function loadLogoDataUrl() {
    if (PDF.logo !== undefined) return PDF.logo;
    try {
        const res = await fetch('/apple-touch-icon.png');
        if (!res.ok) throw new Error(`logo ${res.status}`);
        const blob = await res.blob();
        // FileReader tidak ada di semua runtime (mis. SSR/test); pakai keduanya.
        PDF.logo = typeof FileReader === 'undefined'
            ? `data:${blob.type || 'image/png'};base64,${btoa(String.fromCharCode(...new Uint8Array(await blob.arrayBuffer())))}`
            : await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
    } catch {
        PDF.logo = null;
    }
    return PDF.logo;
}

/** Logo toko untuk kop PDF: PNG data URL dari public/icons, atau null. */
export function warmReceiptLogo() {
    return loadLogoDataUrl();
}

async function buildWithContentHeight(paint) {
    const { jsPDF } = await import('jspdf');
    initPdfLayout();
    await loadLogoDataUrl();

    const make = (heightMm) => new jsPDF({ unit: 'mm', format: [PDF.width, heightMm], orientation: 'landscape', compress: true });

    PDF.height = PAGE_MM;
    const bottom = paint(make(PAGE_MM));

    const contentMm = Math.min(Math.ceil(bottom + PDF.padY), PAGE_MM);
    PDF.height = contentMm;
    const pdf = make(contentMm);
    paint(pdf);
    return pdf;
}

function pdfBlob(pdf, name) {
    if (!pdf || typeof pdf.output !== 'function') return null;
    const base = String(name ?? 'nota').replace(/\.pdf$/i, '').replace(/[^\w.-]+/g, '-').replace(/^-|-$/g, '') || 'nota';
    const filename = `${base}.pdf`;
    return { blob: pdf.output('blob'), filename };
}

/**
 * Nama file nota: "NAMACUSTOM-INVOICE.pdf" (mis. JOKO-INV261018-04.pdf).
 */
export function receiptFilename(customerName, invoice) {
    const customer = String(customerName ?? '')
        .normalize('NFKD').replace(/[^\x00-\x7F]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '')
        || 'CUSTOMER';
    const number = String(invoice ?? 'NOTA')
        .normalize('NFKD').replace(/[^\x00-\x7F]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9-]+/g, '-')
        .replace(/^-|-$/g, '')
        || 'NOTA';

    return `${customer}-${number}`;
}

// Kepala dokumen HTML (dipakai tombol Cetak).
export function storeHead({ store = {}, title = '', invoice = '', badge = null }) {
    const logoHtml = `<img src="/apple-touch-icon.png" alt="logo"
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

export const SALE_FOOTER_NOTES = [
    {
        text: 'Garansi toko berlaku untuk :',
        subs: ['Hardware 60 Hari', 'Software 1 Tahun'],
    },
    {
        text: 'Garansi tukar unit jika tidak cocok, dengan syarat :',
        subs: ['tidak lebih dari 4 hari setelah pembelian', 'penukaran hanya bisa 1x'],
    },
    {
        text: 'Garansi hangus jika terjadi human error / kesalahan pengguna.',
    },
    {
        text: 'Tidak ada pengembalian uang atau barang untuk barang yang sudah dibeli.',
    },
];

/**
 * Catatan footer dalam baris teks datar (mendukung sub-poin).
 * @returns {Array<{text: string, sub: boolean}>}
 */
function flattenNotes(notes) {
    return notes.flatMap((note, i) => {
        const line = typeof note === 'string' ? note : note.text;
        const rows = [{ text: `${i + 1}. ${line}`, sub: false }];
        for (const sub of (typeof note === 'string' ? [] : (note.subs ?? []))) {
            rows.push({ text: `- ${sub}`, sub: true });
        }

        return rows;
    });
}

export const SERVICE_FOOTER_NOTES = [
    'Garansi dengan kerusakan yg sama 1 bln.',
    'Garansi berlaku selama tidak terjadi human error / kesalahan pengguna.',
    'Tidak ada pengembalian uang untuk Servisan yang sudah selesai.',
    'Servisan selesai/cancel yang tidak diambil lebih dari 60 hari di luar tanggung jawab kami.',
];

export function storeFoot(store = {}, { notes = SALE_FOOTER_NOTES, bullet = false, numbered = false } = {}) {
    const body = numbered
        ? flattenNotes(notes)
            .map((row) => (row.sub
                ? `<div style="padding-left:3.5mm">${esc(row.text)}</div>`
                : `<div>${esc(row.text)}</div>`))
            .join('')
        : notes
            .map((line) => (bullet ? `• ${esc(typeof line === 'string' ? line : line.text)}` : esc(typeof line === 'string' ? line : line.text)))
            .join('<br>');

    return `<div class="foot">
        <div>${esc(store.footer ?? 'Terima kasih')}</div>
        <div style="margin-top:4px;font-size:8px;color:#9ca3af;line-height:1.5;${bullet || numbered ? 'text-align:left;' : ''}">
            ${body}
        </div>
    </div>`;
}

// Baris tabel barang: nama, serial, fitur, aksesori, qty, harga, diskon, subtotal.
function itemRows(items = [], { showDiscount = false } = {}) {
    return items.map((li, i) => {
        const qty = li.quantity ?? li.qty ?? 0;
        const price = li.price ?? li.buy_price ?? 0;
        const subtotal = li.subtotal ?? (qty * price);
        const sub = li.serial_number ? `<span>SN: ${esc(li.serial_number)}</span>` : '';
        const detailLines = [
            ...specLines(li.specification).map((line) => `<div class="item-spec">${esc(line)}</div>`),
            Array.isArray(li.features) && li.features.length ? `<div class="item-spec">${esc(li.features.join(' · '))}</div>` : '',
            Array.isArray(li.accessories) && li.accessories.length ? `<div class="item-spec">${esc(li.accessories.join(' · '))}</div>` : '',
            li.warranty ? `<div class="item-spec">Garansi: ${esc(li.warranty)}</div>` : '',
        ].join('');

        return `<tr>
            <td class="idx">${i + 1}</td>
            <td>
                <div class="item-name">${esc(li.item_name ?? li.name ?? '')}</div>
                ${sub ? `<div class="item-sub">${sub}</div>` : ''}
                ${detailLines}
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

// Isi nota penjualan/pembelian (HTML, untuk Cetak).
export function saleBody({ store = {}, sale, title = 'NOTA PENJUALAN' }) {
    if (!sale) return '';

    const items = sale.sale_items || sale.items || [];
    const isPaid = sale.payment_status ? sale.payment_status === 'lunas' : true;
    const isPurchase = sale.supplier !== undefined;
    const itemDiscount = items.reduce((sum, li) => sum + (Number(li.discount) || 0), 0);
    const hasDiscount = Number(sale.discount) > 0;
    const headerDiscount = Number(sale.header_discount) || 0;

    // Kiri: tanggal → kasir → pembayaran. Kanan: customer/supplier → telepon.
    const metaLeft = [
        pair('Tanggal', formatDate(sale.created_at)),
        pair('Kasir', sale.user?.name ?? sale.cashier),
        pair('Pembayaran', sale.payment_method),
        pair('No. Nota Supplier', sale.buy_from_reference),
        pair('Dibayar', sale.paid_at ? formatDate(sale.paid_at) : null),
        pair('DP', sale.dp_amount ? money(sale.dp_amount) : null),
    ].join('');

    const metaRight = [
        pair(isPurchase ? 'Supplier' : 'Customer', sale.customer?.name ?? sale.supplier ?? 'Umum'),
        pair('Telepon', sale.customer?.phone ?? sale.supplier_phone),
    ].join('');

    const meta = `<div>${metaLeft}</div><div>${metaRight}</div>`;

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
        ${storeFoot(store, { numbered: true })}`;
}

/**
 * Nota penjualan / pembelian → PDF vector.
 * @returns {Promise<{ blob: Blob, filename: string }>}
 */
export async function buildSaleReceiptPdf({ store = {}, sale, title = 'NOTA PENJUALAN', filename } = {}) {
    if (!sale) return null;
    const paint = (pdf) => {

        const items = sale.sale_items || sale.items || [];
        const isPaid = sale.payment_status ? sale.payment_status === 'lunas' : true;
        const isPurchase = sale.supplier !== undefined;
        const itemDiscount = items.reduce((sum, li) => sum + (Number(li.discount) || 0), 0);
        const headerDiscount = Number(sale.header_discount) || 0;

        let y = drawHead(pdf, {
            store,
            title,
            invoice: sale.invoice_no ?? sale.reference_no ?? '',
            badge: sale.payment_status ? { label: isPaid ? 'LUNAS' : 'BELUM LUNAS', cls: isPaid ? 'badge-paid' : 'badge-due' } : null,
        });

        // Kiri: tanggal → kasir → pembayaran. Kanan: customer/supplier → telepon.
        y = drawPairs(pdf, [
            [
                ['Tanggal', formatDate(sale.created_at)],
                ['Kasir', sale.user?.name ?? sale.cashier ?? '-'],
                ['Pembayaran', sale.payment_method ?? '-'],
                ...(sale.buy_from_reference ? [['No. Nota Supplier', sale.buy_from_reference]] : []),
                ...(sale.paid_at ? [['Dibayar', formatDate(sale.paid_at)]] : []),
                ...(sale.dp_amount ? [['DP', money(sale.dp_amount)]] : []),
            ].filter(Boolean),
            [
                [isPurchase ? 'Supplier' : 'Customer', sale.customer?.name ?? sale.supplier ?? 'Umum'],
                ...((sale.customer?.phone ?? sale.supplier_phone) ? [['Telepon', sale.customer?.phone ?? sale.supplier_phone]] : []),
            ].filter(Boolean),
        ], y);

        const showDiscount = Number(sale.discount) > 0 || items.some((li) => Number(li.discount) > 0) || headerDiscount > 0;
        const cols = [
            { label: '#', width: 8, align: 'left', color: [156, 163, 175], render: (_, i) => i + 1 },
            {
                label: 'Barang',
                width: showDiscount ? 74 : 96,
                render: (li) => {
                    const rows = [{ text: li.item_name ?? li.name ?? '', style: 'bold' }];
                    const small = { size: 6, color: [107, 114, 128] };

                    if (li.serial_number) {
                        rows.push({ text: `SN: ${li.serial_number}`, ...small });
                    }

                    for (const line of specLines(li.specification)) {
                        rows.push({ text: line, ...small });
                    }

                    if (Array.isArray(li.features) && li.features.length) {
                        rows.push({ text: li.features.join(' · '), ...small });
                    }

                    if (Array.isArray(li.accessories) && li.accessories.length) {
                        rows.push({ text: li.accessories.join(' · '), ...small });
                    }

                    if (li.warranty) {
                        rows.push({ text: `Garansi: ${li.warranty}`, ...small });
                    }

                    return rows;
                },
            },
            { label: 'Qty', width: 10, align: 'right', render: (li) => li.quantity ?? li.qty ?? 0 },
            { label: 'Harga', width: 24, align: 'right', render: (li) => num(li.price ?? li.buy_price ?? 0) },
            showDiscount && { label: 'Diskon', width: 26, align: 'right', render: (li) => (Number(li.discount) > 0 ? `- ${num(li.discount)}` : '-') },
            {
                label: 'Subtotal', width: 0, align: 'right', style: 'bold',
                render: (li) => num(li.subtotal ?? (li.quantity ?? li.qty ?? 0) * (li.price ?? li.buy_price ?? 0)),
            },
        ].filter(Boolean);
        cols[cols.length - 1].width = PDF.widthInner - cols.slice(0, -1).reduce((sum, c) => sum + c.width, 0);

        y = drawTable(pdf, { columns: cols, rows: items, y: y + 1 });

        const totals = [
            ['Subtotal', money(sale.subtotal)],
            itemDiscount > 0 ? ['Diskon item', `- ${money(itemDiscount)}`] : null,
            Number(sale.discount) > 0 ? ['Diskon', `- ${money(sale.discount)}`] : null,
            headerDiscount > 0 ? ['Diskon khusus', `- ${money(headerDiscount)}`] : null,
            ['TOTAL', money(sale.total), true],
        ].filter(Boolean);
        y = drawTotals(pdf, totals, y);

        y = drawNotes(pdf, sale.notes ? `Catatan: ${sale.notes}` : '', y);
        y = drawFoot(pdf, store, y, { bullets: SALE_FOOTER_NOTES, numbered: true });

    return y + 2;
    };

    const pdf = await buildWithContentHeight(paint);
    return pdfBlob(pdf, filename ?? title);
}

/**
 * Nota servis → PDF vector.
 * @returns {Promise<{ blob: Blob, filename: string }>}
 */
export async function buildServiceReceiptPdf({ store = {}, reparation, items = [], total = 0, paid = false, filename } = {}) {
    if (!reparation) return null;
    const paint = (pdf) => {

        let y = drawHead(pdf, { store, title: 'NOTA SERVIS', invoice: reparation.invoice ?? '' });

        y = drawPairs(pdf, [
            ['Tanggal', formatDate(reparation.created_at)],
            ['Customer', reparation.customer_name ?? 'Umum'],
            ['Telepon', reparation.customer_phone ?? '-'],
            ['Status', reparation.status ?? '-'],
        ], y);

        const cols = [
            { label: '#', width: 8, align: 'left', color: [156, 163, 175], render: (_, i) => i + 1 },
            { label: 'Kendala', width: PDF.widthInner - 8 - 34, render: (item) => item.label ?? '', style: 'bold' },
            {
                label: 'Biaya', width: 34, align: 'right',
                render: (item) => (Number(item.cost) > 0 ? money(item.cost) : '-'),
            },
        ];
        y = drawTable(pdf, { columns: cols, rows: items, y: y + 1 });

        y = drawTotals(pdf, [
            ['Pembayaran', paid ? 'Lunas' : 'Belum lunas'],
            ['TOTAL', money(total), true],
        ], y);

        y = drawFoot(pdf, store, y, { bullet: true, bullets: SERVICE_FOOTER_NOTES });

    return y + 2;
    };

    const pdf = await buildWithContentHeight(paint);
    return pdfBlob(pdf, filename);
}

// ===================== Print (HTML, ukuran mengikuti isi) =====================

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
 * Buka WhatsApp dengan teks siap kirim. PDF sudah diunduh lebih dulu, jadi
 * user tinggal tempel lampiran (clip → dokumen) di chat yang terbuka.
 * Router WA: nomor tujuan di path segment pertama; teks di query `text`.
 */
export function openWhatsapp(phone, message = '') {
    const digits = String(phone ?? '').replace(/\D/g, '');
    const number = digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
    if (!number) return false;

    const text = message ? `?text=${encodeURIComponent(message)}` : '';
    // Paksa buka di tab baru (bukan popup yang bisa diblokir browser).
    const opened = window.open(`https://wa.me/${number}${text}`, '_blank');

    if (!opened) {
        // Popup diblokir (biasanya setelah proses async panjang): buka di tab aktif.
        window.location.assign(`https://wa.me/${number}${text}`);
    }

    return true;
}

/**
 * Kirim nota: PDF dibuat → diunduh → chat WhatsApp nomor customer dibuka.
 * @returns {Promise<boolean>} false bila nomor tidak valid.
 */
export async function sendReceiptToWhatsapp({ pdf, phone, message = '', filename }) {
    if (!phone) return false;

    const { blob, filename: name } = pdf ?? {};
    if (!blob) return false;

    downloadBlob(blob, filename ?? name);

    // Buka chat setelah file mulai diunduh; di mobile download berjalan di latar.
    return openWhatsapp(phone, message);
}

/**
 * Cetak nota penjualan / pembelian (HTML).
 */
export function printReceipt({ store = {}, sale, title = 'NOTA PENJUALAN' }) {
    if (!sale) return;
    printReceiptHtml({
        title: sale.invoice_no ?? sale.reference_no ?? 'Nota',
        body: saleBody({ store, sale, title }),
    });
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

