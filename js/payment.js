// =====================
// payment.js (final fixed version)
// =====================

// Ambil parameter dari URL (tetap, jika perlu)
function getQueryParam(key) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(key);
}

// Notifikasi sederhana
function showNotification(message, type = "success") {
  const notif = document.createElement("div");
  notif.className = `notification ${type}`;
  notif.innerText = message;
  document.body.appendChild(notif);
  setTimeout(() => {
    if (notif && notif.parentNode) notif.parentNode.removeChild(notif);
  }, 3000);
}

// Toast mini (dipakai juga untuk error kecil)
function showToast(message, type = "error") {
  const toast = document.createElement("div");
  toast.className = "toast " + type;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add("show"), 10);
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Format tanggal/waktu kecil
function formatDate(date) {
  const d = new Date(date);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const year = d.getFullYear();
  const time = d.toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
  return `${day}/${month}/${year} ${time}`;
}

// Escape HTML (sederhana)
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Tampilkan struk (modal)
function showReceipt(order, method) {
  const dateTime = formatDate(order.paidAt || Date.now());
  const detailsBox = document.getElementById("receiptDetails");
  if (!detailsBox) return;

  let itemsHtml = "<table class='receipt-items'>";
  itemsHtml += "<tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>";

  if (order.items && order.items.length > 0) {
    order.items.forEach((it) => {
      itemsHtml += `
        <tr>
          <td>${escapeHtml(it.nama_produk)}</td>
          <td>${escapeHtml(it.quantity)}</td>
          <td>Rp ${Number(it.subtotal).toLocaleString("id-ID")}</td>
        </tr>`;
    });
  }
  itemsHtml += "</table>";

  const methodText =
    method === "cash"
      ? "Bayar di Kasir"
      : method === "transfer"
      ? "Transfer Bank"
      : "QRIS";

  detailsBox.innerHTML = `
    <div class="receipt-info"><strong>ID:</strong> ${escapeHtml(order.id)}</div>
    <div class="receipt-info"><strong>Nama:</strong> ${escapeHtml(
      order.nama_pemesan
    )}</div>
    <div class="receipt-info"><strong>Meja:</strong> ${escapeHtml(
      order.nomor_meja
    )}</div>
    <div class="receipt-info"><strong>Metode:</strong> ${escapeHtml(
      methodText
    )}</div>
    <div class="receipt-info"><strong>Waktu:</strong> ${escapeHtml(
      dateTime
    )}</div>
    <div class="receipt-line"></div>
    ${itemsHtml}
    <div class="receipt-line"></div>
    <div class="total-line"><strong>Total: Rp ${Number(
      order.total
    ).toLocaleString("id-ID")}</strong></div>
  `;

  const modal = document.getElementById("receiptModal");
  if (modal) modal.style.display = "flex";

  // Download PDF (Elegant Color Edition)
  const downloadBtn = document.getElementById("downloadReceiptBtn");
  if (downloadBtn) {
    downloadBtn.onclick = function () {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF("p", "mm", "a4");

      // Palet warna elegan
      const colorTitle = [77, 54, 43]; // coffee brown
      const colorAccent = [186, 157, 112]; // gold-beige
      const textMain = [50, 50, 50]; // charcoal
      const textSoft = [120, 120, 120]; // light gray
      let y = 25;

      // === HEADER ===
      doc.setFont("times", "bold");
      doc.setFontSize(24);
      doc.setTextColor(...colorTitle);
      doc.text("WARKOP SERAYA", 105, y, { align: "center" });

      y += 6;
      doc.setFont("times", "italic");
      doc.setFontSize(11);
      doc.setTextColor(...textSoft);
      doc.text("Est. 2020", 105, y, { align: "center" });

      y += 8;
      doc.setDrawColor(...colorAccent);
      doc.setLineWidth(0.5);
      doc.line(50, y, 160, y);

      y += 12;
      doc.setFont("helvetica", "normal");
      doc.setFontSize(11);
      doc.setTextColor(...textMain);
      doc.text("Bukti Pembayaran", 105, y, { align: "center" });

      // === INFO PESANAN ===
      y += 14;
      const info = [
        ["ID Pesanan :", order.id],
        ["Nama Pemesan :", order.nama_pemesan],
        ["Nomor Meja :", order.nomor_meja],
        ["Metode Pembayaran :", methodText],
        ["Waktu Transaksi :", dateTime],
      ];

      info.forEach(([label, value]) => {
        y += 6;
        doc.setFont("helvetica", "bold");
        doc.setTextColor(...colorTitle);
        doc.text(`${label}`, 35, y);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(...textMain);
        doc.text(`${value}`, 90, y);
      });

      // === PEMBATAS ===
      y += 10;
      doc.setDrawColor(...colorAccent);
      doc.setLineWidth(0.3);
      doc.line(30, y, 180, y);
      y += 8;

      // === TABEL ITEM ===
      doc.setFont("helvetica", "bold");
      doc.setFontSize(11);
      doc.setTextColor(...colorTitle);
      doc.text("Item", 35, y);
      doc.text("Qty", 120, y);
      doc.text("Subtotal", 175, y, { align: "right" });

      y += 3;
      doc.setDrawColor(...colorAccent);
      doc.setLineWidth(0.2);
      doc.line(30, y, 180, y);
      y += 5;

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10.5);
      doc.setTextColor(...textMain);

      if (order.items && order.items.length > 0) {
        order.items.forEach((it) => {
          doc.text(it.nama_produk, 35, y);
          doc.text(it.quantity.toString(), 122, y, { align: "center" });
          doc.text(
            "Rp " + Number(it.subtotal).toLocaleString("id-ID"),
            175,
            y,
            { align: "right" }
          );

          y += 6;
          doc.setDrawColor(...colorAccent);
          doc.line(32, y, 178, y);
          y += 3;
        });
      }

      y += 6;

      // === TOTAL ===
      doc.setDrawColor(...colorAccent);
      doc.setFillColor(249, 245, 239); // soft beige background
      doc.roundedRect(30, y, 150, 18, 2, 2, "FD");

      doc.setFont("helvetica", "bold");
      doc.setFontSize(12);
      doc.setTextColor(...colorTitle);
      doc.text("TOTAL PEMBAYARAN", 35, y + 12);
      doc.text(
        `Rp ${Number(order.total).toLocaleString("id-ID")}`,
        175,
        y + 12,
        { align: "right" }
      );

      // === FOOTER ===
      y += 35;
      doc.setDrawColor(...colorAccent);
      doc.line(60, y, 150, y);
      y += 10;

      doc.setFont("times", "italic");
      doc.setFontSize(11);
      doc.setTextColor(...colorTitle);
      doc.text("“Warkop Seraya.”", 105, y, {
        align: "center",
      });

      y += 10;
      doc.setFont("helvetica", "normal");
      doc.setFontSize(9);
      doc.setTextColor(...textSoft);
      doc.text("“Terima kasih telah berkunjung”", 105, y, {
        align: "center",
      });

      // === SIMPAN ===
      doc.save("Struk_Pembayaran.pdf");
    };
  }
}

// Tutup struk
function closeReceipt() {
  localStorage.removeItem("cart");
  const modal = document.getElementById("receiptModal");
  if (modal) modal.style.display = "none";
  window.location.href = "/Project/menu.php";
}

// MAIN
document.addEventListener("DOMContentLoaded", () => {
  const orderInfo = document.getElementById("orderInfo");
  const methodInput = document.getElementById("method");
  const rekeningInfo = document.getElementById("rekeningInfo");
  const qrisImage = document.getElementById("qrisImage");
  const emailInputBox = document.getElementById("emailInput");
  const uploadBukti = document.getElementById("uploadBukti");
  const bankNameEl = document.getElementById("bankName");
  const bankNumberEl = document.getElementById("bankNumber");
  const bankOwnerEl = document.getElementById("bankOwner");
  const copyBtn = document.getElementById("copyRekeningBtn");

  if (rekeningInfo) rekeningInfo.style.display = "none";
  if (qrisImage) qrisImage.style.display = "none";
  if (emailInputBox) emailInputBox.style.display = "none";
  if (uploadBukti) uploadBukti.style.display = "none";

  if (typeof orderFromDB === "undefined" || !orderFromDB) {
    if (orderInfo)
      orderInfo.innerHTML =
        "<p style='color:red'>❌ Order tidak ditemukan.</p>";
    return;
  }
  const order = orderFromDB;

  // Pilih metode
  const methods = document.querySelectorAll(".method");
  methods.forEach((m) => {
    m.addEventListener("click", () => {
      methods.forEach((x) => x.classList.remove("active"));
      m.classList.add("active");

      const selected = String(m.dataset.method || "").trim();
      if (methodInput) methodInput.value = selected;

      if (rekeningInfo) rekeningInfo.style.display = "none";
      if (qrisImage) qrisImage.style.display = "none";
      if (emailInputBox) emailInputBox.style.display = "none";
      if (uploadBukti) uploadBukti.style.display = "none";

      if (selected === "transfer") {
        if (rekeningInfo) rekeningInfo.style.display = "block";
        if (bankNameEl) bankNameEl.textContent = "BCA";
        if (bankNumberEl) bankNumberEl.textContent = "4731720686";
        if (bankOwnerEl) bankOwnerEl.textContent = "Warkop Seraya";
        if (emailInputBox) emailInputBox.style.display = "block";
        if (uploadBukti) uploadBukti.style.display = "block";
      } else if (selected === "qris") {
        if (qrisImage) qrisImage.style.display = "block";
        if (emailInputBox) emailInputBox.style.display = "block";
        if (uploadBukti) uploadBukti.style.display = "block";
      }
    });
  });

  // Copy rekening
  if (copyBtn && bankNumberEl) {
    copyBtn.addEventListener("click", async (ev) => {
      ev.preventDefault();
      const text = bankNumberEl.textContent.trim();
      if (!text) return showNotification("Tidak ada nomor rekening.", "error");
      try {
        await navigator.clipboard.writeText(text);
        showNotification("Nomor rekening disalin!", "success");
      } catch {
        showNotification("Gagal menyalin nomor rekening!", "error");
      }
    });
  }

  // Submit form pembayaran
  const paymentForm = document.getElementById("paymentForm");
  if (!paymentForm) return;

  paymentForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const method = methodInput?.value?.trim() || "";
    const emailEl = document.getElementById("email");
    const email = emailEl ? emailEl.value.trim() : "";

    if (!method) return alert("Silakan pilih metode pembayaran!");

    // === FIX LOGIC DI SINI ===
    // Untuk metode cash: kirim ke callback_dummy.php agar backend set status_pesanan -> 'pending'
    if (method === "cash") {
      try {
        const formData = new FormData();
        formData.append("orderId", order.id);
        formData.append("method", method);

        // kirim ke callback_dummy.php agar status pesanan jadi pending di server
        const resp = await fetch("callback_dummy.php", {
          method: "POST",
          body: formData,
        });

        const text = await resp.text();
        console.log("Response dari callback_dummy.php (cash):", text);

        // jika backend mengembalikan pesan error dalam JSON, coba parse dan tampilkan
        try {
          const parsed = JSON.parse(text);
          if (parsed && parsed.success === false) {
            alert("Gagal: " + (parsed.message || "Error dari server"));
            return;
          }
        } catch (_) {
          // tidak JSON, tetap lanjut (server mungkin redirect)
        }

        showNotification(
          "Pesanan berhasil dibuat. Silakan bayar di kasir.",
          "success"
        );

        // Tunggu sedikit biar user lihat notifikasi lalu bersihkan cart & redirect
        setTimeout(() => {
          localStorage.removeItem("cart");
          window.location.href = "/Project/menu.php";
        }, 1200);
      } catch (err) {
        console.error("❌ Error saat proses bayar di kasir:", err);
        alert("Gagal memproses pesanan dengan metode Bayar di Kasir.");
      }
      return;
    }
    // === END FIX LOGIC CASH ===

    // === FIX: LOGIC TRANSFER & QRIS ===
    if (method === "transfer" || method === "qris") {
      const emailEl = document.getElementById("email");
      const email = emailEl ? emailEl.value.trim() : "";

      if (email === "") {
        showToast("Masukkan alamat email!", "error");
        return;
      }

      const buktiEl = document.getElementById("buktiBayar");
      const bukti = buktiEl?.files ? buktiEl.files[0] : null;
      if (!bukti) {
        showToast("Silakan upload bukti pembayaran!", "error");
        return;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        alert("Format email tidak valid!");
        return;
      }

      try {
        const formData = new FormData();
        formData.append("orderId", order.id);
        formData.append("method", method); // ✅ PENTING: kirim ke PHP
        formData.append("email", email);
        formData.append("total", order.total);
        formData.append("buktiBayar", bukti);

        console.log("📤 Mengirim ke payment.php dengan method:", method);

        const resp = await fetch("payment.php", {
          method: "POST",
          body: formData,
        });

        const text = await resp.text();
        console.log("📥 Respon dari server:", text);

        let result = {};
        try {
          result = JSON.parse(text);
        } catch (e) {
          console.warn("Response bukan JSON:", text);
        }

        if (result.success === false) {
          alert("❌ " + (result.message || "Gagal memproses pembayaran."));
          return;
        }

        showNotification("Pembayaran berhasil dikonfirmasi.", "success");
        order.paidAt = new Date().toISOString();
        order.method = method;
        showReceipt(order, method);
      } catch (err) {
        console.error("❌ Error submit pembayaran:", err);
        alert("Terjadi kesalahan saat memproses pembayaran.");
      }

      return; // ✅ stop agar tidak lanjut ke bawah
    }
    // === END FIX LOGIC TRANSFER & QRIS ===
  });
}); // end DOMContentLoaded
