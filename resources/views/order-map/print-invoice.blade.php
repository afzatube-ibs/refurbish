{{-- Lokkisona invoice layout ported from reference/lokkisona-custom-invoice/ (reference only; not loaded at runtime). --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Packing Invoice — ORDER #{{ $invoice['order_id'] }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<script src="{{ asset('js/lokkisona-qrcode.min.js') }}"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
:root{--brand:#754E3B;--brand-dark:#5d3728;--brand-soft:#b99686;--line:#eadbd3;--cream:#fbf6f3;--text:#241b18;--muted:#645854}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#f5f3f1;color:var(--text);font-family:'Poppins',Arial,Helvetica,sans-serif;font-size:10px;line-height:1.32;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.print-actions{position:sticky;top:0;z-index:10;background:#fff;padding:7px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.print-actions button,.print-actions a.btn-print-back{display:inline-block;background:var(--brand);color:#fff;border:0;border-radius:6px;padding:7px 15px;font-weight:600;cursor:pointer;text-decoration:none;margin:0 4px;font-size:10px;line-height:1.32}
.print-actions a.btn-print-back.secondary{background:#645854}
.invoice-page{width:190mm;min-height:277mm;margin:8px auto;padding:6mm 6mm 7mm;background:#fff;box-shadow:0 10px 28px rgba(117,78,59,.09);page-break-after:always;overflow:hidden}
.top{display:grid;grid-template-columns:27% 41% 32%;gap:9px;align-items:center;padding-bottom:9px;border-bottom:1.4px solid var(--brand)}
.logo{max-width:150px;max-height:78px;object-fit:contain}
.store{border-left:1px solid var(--brand-soft);padding-left:14px;min-width:0}.store-title{font-size:14px;font-weight:700;margin-bottom:5px}.store-line{font-size:9.7px;margin:3px 0;line-height:1.35}.store-ico{display:none}.store-line strong{font-weight:600}.head{text-align:right;min-width:0;padding-right:4px}.invoice-word{font-size:14px;letter-spacing:5px;color:#d9c3ba;font-weight:600;text-transform:uppercase;margin-bottom:8px;white-space:nowrap}.order-box{display:inline-block;max-width:100%;background:linear-gradient(90deg,#7b4229,var(--brand));color:#fff;border-radius:4px;padding:6px 10px;font-size:16px;font-weight:600;letter-spacing:.2px;white-space:nowrap}.head-meta{font-size:10.2px;margin-top:6px;line-height:1.55;white-space:nowrap}.info-grid{display:grid;grid-template-columns:29% 36% 35%;gap:0;margin-top:12px;padding-bottom:11px;border-bottom:1px solid var(--line)}.info-card{position:relative;padding:0 10px;min-height:88px;border-right:1px solid #d9bfb4;min-width:0}.info-card:last-child{border-right:0}.icon,.info-card:before,.label:before,.store-line:before{display:none!important;content:''!important}.label{font-weight:600;text-transform:uppercase;font-size:11px;margin:3px 0 12px;letter-spacing:.1px}.field{display:grid;grid-template-columns:70px 7px minmax(0,1fr);gap:3px;margin:7px 0;font-size:10.4px;align-items:center;white-space:nowrap}.field strong,.bold{font-weight:400;word-break:normal;overflow-wrap:normal;white-space:nowrap}.field span{min-width:0;white-space:nowrap}.address{font-size:10.4px;line-height:1.45;word-break:normal;overflow-wrap:break-word}.ship-field{display:grid;grid-template-columns:96px 7px minmax(0,1fr);gap:4px;margin:6px 0;font-size:10px;align-items:center;white-space:nowrap}.ship-field b{font-weight:600;white-space:nowrap}.ship-field span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pay-logo{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:15px;margin-right:5px;border-radius:3px;font-size:8.5px;font-weight:700;line-height:1;text-transform:uppercase;vertical-align:middle}.pay-logo-bkash{color:#e2136e;background:#fff0f7;border:1px solid #f6bad4}.pay-logo-cod{color:#754E3B;background:#fbf6f3;border:1px solid #dcc7bd}.courier-logo{display:inline-block;margin-left:5px;color:#1266b0;font-size:8.5px;font-weight:700;font-style:italic}.summary-strip{display:grid;grid-template-columns:118px 1fr;gap:14px;align-items:center;margin:10px 0}.seal{width:78px;height:78px;border:1.4px solid var(--brand);border-radius:50%;color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:700;letter-spacing:.8px;transform:rotate(-12deg);opacity:.62}.seal-paid{color:#2f7b45;border-color:#2f7b45}.summary-box{margin-left:auto;display:grid;grid-auto-flow:column;grid-auto-columns:minmax(96px,1fr);border:1px solid #e2c9be;border-radius:5px;overflow:hidden;max-width:430px;width:100%}.summary-item{padding:7px 9px;text-align:center;border-right:1px solid #e2c9be;background:#fffdfb}.summary-item:last-child{border-right:0;background:#fcf2ea}.summary-title{font-size:10.3px;font-weight:400}.summary-value{margin-top:4px;font-size:12px;font-weight:400}.summary-grand .summary-title{color:var(--brand);font-weight:600;text-transform:uppercase}.summary-grand .summary-value{color:var(--brand);font-size:20px;font-weight:700}.items{width:100%;border-collapse:separate;border-spacing:0;margin-top:8px;border:1px solid #e2c9be;border-radius:4px;overflow:hidden;table-layout:fixed}.items th{background:linear-gradient(90deg,#7b4229,var(--brand));color:#fff;text-transform:uppercase;font-size:9.3px;font-weight:600;padding:6px 5px;border-right:1px solid rgba(255,255,255,.35);white-space:nowrap}.items th:last-child{border-right:0}.items td{border-top:1px solid #eadbd3;border-right:1px solid #eadbd3;padding:6px 6px;vertical-align:top;font-size:9.8px;line-height:1.32}.items td:last-child{border-right:0}.col-no{width:25px;text-align:center}.col-img{width:80px;text-align:center}.col-model{width:82px;text-align:center}.col-qty{width:42px;text-align:center}.col-price,.col-total{width:78px;text-align:right}.product-image{width:58px;height:58px;object-fit:contain;border:1px solid #e5d1c8;border-radius:5px;padding:2px;background:#fff}.pname{font-size:9.7px;font-weight:500;line-height:1.35;margin:1px 0 5px;word-break:normal;overflow-wrap:break-word}.option-line{font-size:9.6px;margin:2px 0;color:#2c2421;word-break:normal;overflow-wrap:break-word}.option-line:before{content:''}.money{font-size:9.8px}.money strong,.col-total strong{font-weight:600}.courier-wrap{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;padding-top:12px;border-top:1.4px dashed var(--brand)}.courier-card,.track-card{border:1px solid #e2c9be;border-radius:5px;background:linear-gradient(90deg,#fffaf6,#fff);padding:10px 12px;min-height:92px}.courier-title,.track-title{font-size:11.5px;font-weight:700;text-transform:uppercase;color:#321d17;margin-bottom:6px}.courier-title span{display:none}.consign-label{font-size:10.5px}.consign-no{font-size:26px;line-height:1.02;font-weight:800;margin:3px 0 5px;letter-spacing:.8px;border-bottom:1px dashed #c9a99c;display:inline-block;padding-bottom:4px}.store-id{font-size:10.5px}.store-id strong{font-weight:600}.track-card{display:grid;grid-template-columns:1fr 94px;gap:10px;align-items:center}.qr-box{width:90px;height:90px;border:1px solid #dcc7bd;border-radius:4px;padding:4px;background:#fff;display:flex;align-items:center;justify-content:center}.qr-box img,.qr-box canvas{max-width:80px!important;max-height:80px!important}.track-text{font-size:10.5px;line-height:1.45}.footer{text-align:center;margin-top:10px;font-size:10.8px;color:#453b36}.footer strong{font-weight:600}.policy{margin-top:8px;border:1px solid #e8ceb7;background:#fff7ef;border-radius:5px;padding:7px 10px;font-size:10.2px}.policy-icon{display:none}.no-data{color:#777}
@page{size:A4;margin:7mm}
@media print{html,body{background:#fff;font-size:10px}.print-actions{display:none}.invoice-page{width:100%;min-height:auto;margin:0;padding:0;box-shadow:none;page-break-after:always;overflow:hidden}.invoice-page:last-child{page-break-after:auto}.top{grid-template-columns:27% 41% 32%;gap:8px;padding-bottom:8px}.logo{max-width:140px;max-height:72px}.store{padding-left:12px}.store-title{font-size:13px}.store-line{font-size:9.2px}.invoice-word{font-size:13px;letter-spacing:4px;margin-bottom:6px}.order-box{font-size:15px;padding:5px 9px}.head-meta{font-size:9.3px}.info-grid{margin-top:10px;padding-bottom:9px}.info-card{min-height:80px;padding:0 8px}.label{font-size:10.2px;margin-bottom:10px}.field{font-size:9.4px;grid-template-columns:64px 6px minmax(0,1fr);white-space:nowrap}.address{font-size:9.5px}.ship-field{font-size:9px;grid-template-columns:88px 6px minmax(0,1fr);margin:5px 0;white-space:nowrap}.pay-logo{min-width:25px;height:13px;font-size:7.6px;margin-right:4px}.courier-logo{font-size:7.8px}.summary-strip{margin:8px 0;grid-template-columns:100px 1fr}.seal{width:68px;height:68px;font-size:16px}.summary-box{max-width:405px}.summary-item{padding:6px 8px}.summary-title{font-size:9.4px}.summary-value{font-size:11px}.summary-grand .summary-value{font-size:18px}.items{margin-top:7px}.items th{font-size:8.6px;padding:5px 4px}.items td{font-size:9px;padding:5px}.col-no{width:22px}.col-img{width:70px}.col-model{width:75px}.col-qty{width:38px}.col-price,.col-total{width:70px}.product-image{width:50px;height:50px}.pname{font-size:9.2px}.option-line{font-size:8.8px}.courier-wrap{margin-top:12px;padding-top:10px}.courier-card,.track-card{min-height:86px;padding:9px 10px}.courier-title,.track-title{font-size:10.5px}.consign-no{font-size:24px}.track-card{grid-template-columns:1fr 84px}.qr-box{width:82px;height:82px}.qr-box img,.qr-box canvas{max-width:74px!important;max-height:74px!important}.footer{font-size:10px;margin-top:8px}.policy{padding:6px 8px;font-size:9.4px}}
.invoice-word{font-size:12px!important;letter-spacing:4px!important;font-weight:500!important;color:#d8c4bb!important;line-height:1.1!important}
.order-box{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:32px!important;line-height:1!important;padding:6px 10px!important;vertical-align:middle!important}
.field,.ship-field{white-space:nowrap!important;word-break:normal!important;overflow-wrap:normal!important}
.field strong,.ship-field b{font-weight:500!important;white-space:nowrap!important}
.field span,.ship-field span{white-space:nowrap!important;word-break:normal!important;overflow-wrap:normal!important;overflow:hidden!important;text-overflow:ellipsis!important}
.pay-logo,.courier-logo{height:13px!important;min-width:24px!important;padding:0 4px!important;margin-right:4px!important;font-size:7.5px!important;line-height:12px!important;border-radius:3px!important;font-weight:700!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;vertical-align:middle!important}
.pay-logo-bkash{color:#e2136e!important;background:#fff!important;border:1px solid #f1b5cf!important}
.pay-logo-cod{color:#754E3B!important;background:#fbf6f3!important;border:1px solid #dcc7bd!important}
.courier-logo{color:#00a651!important;background:#fff!important;border:1px solid #9ed9b8!important;font-style:normal!important;max-width:42px!important;overflow:hidden!important}
.courier-title{display:none!important}
.courier-wrap{grid-template-columns:1fr 118px!important;gap:8px!important;margin-top:7px!important}
.courier-card{padding:7px 10px!important;min-height:58px!important}
.consign-label{font-size:8.5px!important;margin-bottom:2px!important;color:#00a651!important}
.consign-no{font-size:16px!important;color:#00a651!important;line-height:1.1!important}
.store-id{font-size:8.8px!important;margin-top:3px!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.track-card{padding:6px 7px!important;min-height:58px!important}
.track-title{font-size:8.8px!important}
.track-text{display:none!important}
.qr-box{width:46px!important;height:46px!important;min-width:46px!important}
.qr-box canvas,.qr-box img{width:46px!important;height:46px!important}
.items th,.items td{font-size:9px!important}
.pname{font-size:9.5px!important;font-weight:500!important}
.option-line{font-size:8.5px!important}
.summary-strip{margin:8px 0!important}
.seal{width:64px!important;height:64px!important;font-size:15px!important;font-weight:600!important}
@media print{
  @page{size:A4;margin:5mm}
  html,body{background:#fff!important}
  .print-actions{display:none!important}
  .invoice-page{width:200mm!important;min-height:auto!important;margin:0 auto!important;padding:4mm 5mm 5mm!important;box-shadow:none!important;page-break-after:always!important;overflow:visible!important}
  .invoice-word{font-size:11px!important;letter-spacing:3px!important}
  .order-box{min-height:30px!important}
  .courier-wrap{margin-top:6px!important}
}
.method-logo-img{display:inline-block!important;vertical-align:middle!important;object-fit:contain!important;max-height:13px!important;max-width:58px!important;width:auto!important;margin:0!important}
.method-logo-bkash{max-height:14px!important;max-width:50px!important}
.method-logo-steadfast{max-height:13px!important;max-width:60px!important}
.pay-logo-cod{height:13px!important;min-width:25px!important;padding:0 5px!important;font-size:7.5px!important}
.logo-svg{display:inline-block!important;vertical-align:middle!important;line-height:1!important}
.logo-svg svg{display:block!important;height:auto!important}
.logo-steadfast svg{width:54px!important;height:15px!important}
.logo-bkash svg{width:42px!important;height:14px!important}
</style>
</head>
<body>
<div class="print-actions">
    <a href="{{ route('order-map.index') }}" class="btn-print-back secondary">Back to Order Queue</a>
    <button type="button" onclick="window.print()">Print Invoice</button>
</div>

<section class="invoice-page">
    <div class="top">
        <div><img class="logo" src="{{ $invoice['store_logo'] }}" alt="{{ $invoice['store_name'] }}" /></div>
        <div class="store">
            <div class="store-title">Lokkisona Baby Store</div>
            <div class="store-line">House: 1, Block C,<br>Dhaka Udyan Main Road,<br>Mohammadpur, Dhaka.</div>
            <div class="store-line"><strong>{{ $invoice['store_phone'] }}</strong></div>
            <div class="store-line">{{ $invoice['store_url'] }}</div>
        </div>
        <div class="head">
            <div class="invoice-word">Invoice</div>
            <div class="order-box">ORDER #{{ $invoice['order_id'] }}</div>
            <div class="head-meta">Invoice No: <span>{{ $invoice['invoice_no'] }}</span><br>Order Date: <span>{{ $invoice['order_date'] }}</span></div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="label">Customer Details</div>
            <div class="field"><span>Name</span><span>:</span><strong>{{ $invoice['customer_name'] }}</strong></div>
            <div class="field"><span>Contact No</span><span>:</span><strong>{{ $invoice['customer_phone'] }}</strong></div>
        </div>
        <div class="info-card">
            <div class="label">Delivery Address</div>
            <div class="address">
                @if ($invoice['shipping_address'] !== '')
                    {{ $invoice['shipping_address'] }}
                @else
                    <span class="no-data">No shipping address found</span>
                @endif
            </div>
        </div>
        <div class="info-card">
            <div class="label">Order &amp; Shipping</div>
            <div class="ship-field"><b>Shipping Method</b><em>:</em><span>
                @if ($invoice['has_steadfast'] || str_contains(strtolower((string) $invoice['shipping_method']), 'steadfast'))
                    <span class="logo-svg logo-steadfast" title="Steadfast"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 42" aria-label="Steadfast"><path d="M8 20 L31 8 L24 20 L35 20 L14 34 L20 23 Z" fill="#00a884"/><text x="42" y="25" font-family="Arial,Helvetica,sans-serif" font-size="23" font-style="italic" font-weight="700" fill="#555">Stead</text><text x="103" y="25" font-family="Arial,Helvetica,sans-serif" font-size="23" font-style="italic" font-weight="700" fill="#00a884">Fast</text><text x="108" y="36" font-family="Arial,Helvetica,sans-serif" font-size="7" letter-spacing="2" fill="#555">Courier</text></svg></span>
                @else
                    {{ $invoice['shipping_method'] ?: 'Not available' }}
                @endif
            </span></div>
            <div class="ship-field"><b>Payment Method</b><em>:</em><span>
                @if ($invoice['payment_logo'] === 'bkash')
                    <span class="logo-svg logo-bkash" title="bKash"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 78 24" aria-label="bKash"><path d="M8 4 L22 11 L8 20 L12 12 Z" fill="#e2136e"/><path d="M22 3 L34 11 L22 21 L25 12 Z" fill="#d6005d"/><text x="37" y="16" font-family="Arial,Helvetica,sans-serif" font-size="12" font-weight="700" fill="#333">bKash</text></svg></span>
                @elseif ($invoice['payment_logo'] === 'cod')
                    <i class="pay-logo pay-logo-cod">COD</i>
                @else
                    {{ $invoice['payment_method'] ?: 'Not available' }}
                @endif
            </span></div>
            <div class="ship-field"><b>Payment Status</b><em>:</em><span>{{ $invoice['payment_status'] }}</span></div>
            <div class="ship-field"><b>Order Date</b><em>:</em><span>{{ $invoice['order_date'] }}</span></div>
        </div>
    </div>

    <div class="summary-strip">
        <div class="seal @if ($invoice['payment_status'] === 'Paid') seal-paid @endif">{{ strtoupper($invoice['payment_status']) }}</div>
        <div class="summary-box">
            @foreach ($invoice['totals'] as $total)
                <div class="summary-item @if ($loop->last) summary-grand @endif">
                    <div class="summary-title">{{ $total['title'] }}</div>
                    <div class="summary-value">{{ $total['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-img">Image</th>
                <th>Product &amp; Options</th>
                <th class="col-model">Model / SKU</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Unit Price</th>
                <th class="col-total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice['products'] as $product)
                <tr>
                    <td class="col-no">{{ $loop->iteration }}</td>
                    <td class="col-img"><img class="product-image" src="{{ $product['image'] }}" alt="{{ $product['name'] }}" /></td>
                    <td>
                        <div class="pname">{{ $product['name'] }}</div>
                        @foreach ($product['options'] as $option)
                            <div class="option-line">{{ $option['name'] }}: {{ $option['value'] }}</div>
                        @endforeach
                    </td>
                    <td class="col-model">{{ $product['model'] }}</td>
                    <td class="col-qty">{{ $product['quantity'] }}</td>
                    <td class="col-price money">{{ $product['price'] }}</td>
                    <td class="col-total money"><strong>{{ $product['total'] }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="courier-wrap">
        <div class="courier-card">
            <div class="courier-title"></div>
            <div class="consign-label">Consignment ID</div>
            <div class="consign-no">
                @if ($invoice['steadfast']['consignment_id'] !== '')
                    {{ $invoice['steadfast']['consignment_id'] }}
                @elseif ($invoice['steadfast']['parcel_id'] !== '')
                    {{ $invoice['steadfast']['parcel_id'] }}
                @else
                    <span class="no-data">Not available</span>
                @endif
            </div>
        </div>
        <div class="track-card">
            <div>
                <div class="track-title">Track Your Parcel</div>
                <div class="track-text">Scan the QR code to track<br>your parcel status.</div>
            </div>
            <div class="qr-box"@if ($invoice['qr_payload'] !== '') data-qr="{{ $invoice['qr_payload'] }}"@endif>
                @if ($invoice['qr_payload'] === '')
                    <span class="no-data">No QR</span>
                @endif
            </div>
        </div>
    </div>

    <div class="footer">
        Thank you for shopping with <strong>Lokkisona Baby Store.</strong><br>
        For order support, call or WhatsApp: <strong>+8801932263545</strong>
        <div class="policy"><span class="policy-icon">✓</span>For any faulty product, replacement request must be informed within 24 hours of receiving the product.</div>
    </div>
</section>

<script>
document.querySelectorAll('.qr-box[data-qr]').forEach(function (el) {
    var payload = el.getAttribute('data-qr');
    if (payload && typeof QRCode !== 'undefined') {
        el.innerHTML = '';
        new QRCode(el, { text: payload, width: 80, height: 80, correctLevel: QRCode.CorrectLevel.M });
    }
});
</script>
</body>
</html>
