const SETTINGS_KEY = 'pos_store_settings'

function readStoreInfo() {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) throw new Error('no settings')

    const parsed = JSON.parse(raw)
    return {
      store_name: String(parsed?.store_name || 'Toko Anda'),
      store_address: String(parsed?.store_address || 'Jl. Contoh Alamat No. 123'),
      store_phone: String(parsed?.store_phone || ''),
    }
  } catch {
    return {
      store_name: 'Toko Anda',
      store_address: 'Jl. Contoh Alamat No. 123',
      store_phone: '',
    }
  }
}

export function printReceipt(transaction) {
  if (!transaction) return

  const storeInfo = readStoreInfo()
  const storePhone = storeInfo.store_phone ? `<p>Telp: ${storeInfo.store_phone}</p>` : ''
  const customerName = String(transaction.customer_name || transaction.customer?.name || '-')

  const detailRows = (transaction.details ?? [])
    .map((item) => `<tr><td>${item.product_name_snapshot} <br><small>x${item.quantity}</small></td><td style="text-align:right;">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td></tr>`)
    .join('')

  const receivedAmountFromPayments = Array.isArray(transaction.payments)
    ? transaction.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
    : 0
  const receivedAmount = receivedAmountFromPayments > 0
    ? receivedAmountFromPayments
    : Number(transaction.cash_received ?? transaction.amount_paid ?? 0)

  const html = `
    <html>
      <head>
        <title>Struk ${transaction.invoice_number}</title>
        <style>
          @page { margin: 0; }
          body { font-family: 'Courier New', Courier, monospace; width: 58mm; margin: 10px auto; color: #000; }
          .text-center { text-align: center; }
          h3 { margin: 0; font-size: 16px; }
          p { margin: 4px 0; font-size: 12px; }
          table { width: 100%; border-collapse: collapse; margin-top: 10px; }
          td { font-size: 12px; padding: 4px 0; vertical-align: top; }
          .line { border-top: 1px dashed #000; margin: 10px 0; }
          .bold { font-weight: bold; }
        </style>
      </head>
      <body>
        <div class="text-center">
          <h3>${storeInfo.store_name}</h3>
          <p>${storeInfo.store_address}</p>
          ${storePhone}
          <div class="line"></div>
          <p>${new Date(transaction.created_at ?? Date.now()).toLocaleString('id-ID')}</p>
          <p>Inv: ${transaction.invoice_number}</p>
          <p>Pembeli: ${customerName}</p>
        </div>
        <div class="line"></div>
        <table>${detailRows}</table>
        <div class="line"></div>
        <table>
          <tr><td class="bold">Total Pembayaran</td><td style="text-align:right;" class="bold">Rp ${Number(transaction.grand_total).toLocaleString('id-ID')}</td></tr>
          <tr><td>Uang Diterima</td><td style="text-align:right;">Rp ${Number(receivedAmount).toLocaleString('id-ID')}</td></tr>
          <tr><td>Kembali</td><td style="text-align:right;">Rp ${Number(transaction.cash_change ?? 0).toLocaleString('id-ID')}</td></tr>
        </table>
        <div class="line"></div>
        <p class="text-center">Terima Kasih<br>Silakan Berkunjung Kembali</p>
      </body>
    </html>
  `

  const popup = window.open('', '_blank', 'width=400,height=600')
  if (!popup) return
  popup.document.write(html)
  popup.document.close()
  popup.focus()
  popup.print()
}
