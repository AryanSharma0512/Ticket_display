/* -------------------------------------------------------------
 *  invoice.js  –  helper for the “Invoices” feature
 * -------------------------------------------------------------
 *  1. User enters a Transaction‑ID (TXN) and clicks “Fetch Invoice”.
 *  2. We call  invoice_check.php?txn=TXN            →  { "found": true|false }
 *  3. If found, we display a download link pointing to
 *        generate_invoice.php?transaction_id=TXN   (opens PDF)
 * -------------------------------------------------------------
 *  © 2025  Dhanlaxmi Travels Ltd.  –  internal use only
 * ----------------------------------------------------------- */

(function () {
  /** Util: safely get an element by ID. */
  function $(id) {
    return document.getElementById(id);
  }

  /**
   * Main entry – checks for invoice existence then updates UI.
   * Bound in admin_panel.html via inline   onclick="fetchInvoice()".
   */
  async function fetchInvoice() {
    // Fix: use the correct element ID.
    const txnInput = $('invoiceTransactionID');
    const output   = $('outputContainer');

    if (!txnInput || !output) {
      console.error('[invoice.js] Required DOM nodes not present.');
      alert('Setup error: missing invoice elements.');
      return;
    }

    const txn = txnInput.value.trim().toUpperCase();

    if (!txn) {
      alert('Please enter a Transaction ID.');
      txnInput.focus();
      return;
    }

    // Show a spinner / simple “checking…” message
    output.innerHTML = '<p>Checking invoice status…</p>';

    try {
      // ————————————— 1)  Call the checker —————————————
      const res = await fetch(
        `invoice_check.php?txn=${encodeURIComponent(txn)}`,
        { cache: 'no-cache' }
      );

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      // The checker must return JSON like:  { "found": true }
      const data = await res.json();

      // ————————————— 2)  Update UI —————————————
      if (data && data.found) {
        // Build link using generate_invoice.php; script accepts either
        //  ?transaction_id=…  OR  ?txn=…
        const pdfUrl = `generate_invoice.php?transaction_id=${encodeURIComponent(txn)}`;

        output.innerHTML = `
          <p>
            ✅ Invoice found — 
            <a href="${pdfUrl}" target="_blank" rel="noopener noreferrer">
              Download PDF
            </a>
          </p>`;
      } else {
        output.innerHTML =
          `<p style="color:#c00;">❌ No invoice on record for TXN ${txn}</p>`;
      }
    } catch (err) {
      console.error('[invoice.js] Error:', err);
      output.innerHTML =
        `<p style="color:#c00;">Error checking invoice. Please retry.</p>`;
    }
  }

  // ------------------------------------------------------------
  // Optional binding: if you DON’T use inline onclick in HTML,
  // uncomment the following block and give the button:
  //      <button id="fetchInvoiceBtn">Fetch Invoice</button>
  // ------------------------------------------------------------
  /*
  document.addEventListener('DOMContentLoaded', () => {
    const btn = $('fetchInvoiceBtn');
    if (btn) btn.addEventListener('click', fetchInvoice);
  });
  */

  // Expose to global scope for inline handler.
  window.fetchInvoice = fetchInvoice;
})();
