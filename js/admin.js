document.addEventListener("DOMContentLoaded", function () {
  // ===================================
  // ✅ WELCOME SPLASH ENTRANCE ANIMATION
  // ===================================
  const splash = document.getElementById("welcomeSplash");
  if (splash) {
    document.body.style.overflow = "hidden";

    const progressBar = splash.querySelector(".splash-progress-bar");
    if (progressBar) {
      setTimeout(() => { progressBar.style.width = "100%"; }, 150);
    }

    setTimeout(() => {
      splash.classList.add("fade-out");
      document.body.style.overflow = "";
    }, 2000);

    setTimeout(() => { splash.remove(); }, 2600);
  }

  // =====================
  // ✅ TOAST NOTIFICATION ADMIN
  // =====================
  window.showAdminToast = function(message, type = "success") {
    let toast = document.getElementById("admin-toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "admin-toast";
      document.body.appendChild(toast);
    }
    
    let icon = type === 'success' ? '<i data-feather="check-circle"></i>' : '<i data-feather="alert-circle"></i>';
    toast.className = `admin-toast ${type}`;
    toast.innerHTML = `${icon} <span>${message}</span>`;
    if (typeof feather !== 'undefined') feather.replace();
    
    // trigger animation
    setTimeout(() => {
      toast.classList.add("show");
    }, 10);
    
    setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  };

  // =====================
  // ✅ Modal Logout
  // =====================
  const logoutBtn = document.getElementById("logoutBtn");
  const modal = document.getElementById("logoutModal");
  const confirmLogout = document.getElementById("confirmLogout");
  const cancelLogout = document.getElementById("cancelLogout");

  const openModal = () => {
    if (!modal) return;
    modal.classList.remove("hide");
    modal.classList.add("show");
    document.body.classList.add("modal-open");
  };
  const closeModal = () => {
    if (!modal) return;
    modal.classList.add("hide");
    setTimeout(() => {
      modal.classList.remove("show");
      modal.classList.remove("hide");
      document.body.classList.remove("modal-open");
    }, 280);
  };

  if (logoutBtn) {
    logoutBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal();
    });
  }
  if (cancelLogout) cancelLogout.addEventListener("click", closeModal);
  if (confirmLogout) {
    confirmLogout.addEventListener("click", () => {
      window.location.href = "../logout.php";
    });
  }
  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });
  }

  // =====================
  // ✅ Sidebar Toggle (versi fix universal)
  // =====================
  const menuToggle = document.getElementById("menu-toggle");
  const sidebar = document.querySelector(".sidebar");

  if (menuToggle && sidebar) {
    // Klik tombol toggle → buka/tutup sidebar
    menuToggle.addEventListener("click", function (e) {
      e.stopPropagation();
      sidebar.classList.toggle("active");
    });

    // Klik di luar sidebar → tutup sidebar
    document.addEventListener("click", function (e) {
      if (
        sidebar.classList.contains("active") &&
        !sidebar.contains(e.target) &&
        e.target !== menuToggle
      ) {
        sidebar.classList.remove("active");
      }
    });
  }

  // Tekan ESC → tutup sidebar (tanpa panggil closeModal)
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (sidebar && sidebar.classList.contains("active")) {
        sidebar.classList.remove("active");
      }
    }
  });

  // =====================
  // ✅ Chart Pendapatan + Filter Tanggal
  // =====================
  const salesChartEl = document.getElementById("salesChart");
  const salesPeriodSelect = document.getElementById("salesPeriod");
  const totalPendapatanEl = document.getElementById("totalPendapatan");
  const totalTransaksiEl = document.getElementById("totalTransaksi");
  const dateRange = document.getElementById("dateRange");
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  const applyDate = document.getElementById("applyDate");

  if (salesChartEl && salesPeriodSelect) {
    const ctx = salesChartEl.getContext("2d");
    let chartInstance;

    // 🎯 Ambil data dari server
    async function loadChart(periode = "today", start = null, end = null) {
      try {
        let url = `get_pendapatan.php?periode=${periode}`;
        if (periode === "custom" && start && end) {
          url += `&start=${start}&end=${end}`;
        }

        const res = await fetch(url);
        const result = await res.json();

        const labels = result.labels || [];
        const data = result.data || [];
        const totalPendapatan = result.total_pendapatan || 0;
        const totalTransaksi = result.total_transaksi || 0;

        // 🧾 Update total
        totalPendapatanEl.textContent =
          "Rp " + totalPendapatan.toLocaleString("id-ID");
        totalTransaksiEl.textContent = totalTransaksi + " Transaksi";

        const chartType = "line"; // Selalu gunakan line chart untuk estetika premium
        const chartTitle =
          periode === "today"
            ? "Pendapatan Hari Ini"
            : periode === "week"
            ? "Pendapatan Minggu Ini"
            : periode === "month"
            ? "Pendapatan Bulan Ini"
            : `Pendapatan ${start} s/d ${end}`;

        // 🔄 Reset chart lama
        if (chartInstance) chartInstance.destroy();

        // 🎨 Buat chart baru dengan efek Area Chart yang smooth
        chartInstance = new Chart(ctx, {
          type: chartType,
          data: {
            labels: labels,
            datasets: [
              {
                label: "Pendapatan",
                data: data,
                backgroundColor: (context) => {
                  const chart = context.chart;
                  const { ctx, chartArea } = chart;
                  if (!chartArea) return null;
                  const gradient = ctx.createLinearGradient(
                    0,
                    chartArea.top,
                    0,
                    chartArea.bottom
                  );
                  gradient.addColorStop(0, "rgba(220, 20, 60, 0.4)");
                  gradient.addColorStop(1, "rgba(220, 20, 60, 0.0)");
                  return gradient;
                },
                borderColor: "rgba(220, 20, 60, 1)",
                borderWidth: 3,
                fill: true,
                tension: 0.45, // Membuat garis melengkung halus
                pointBackgroundColor: "#fff",
                pointBorderColor: "rgba(220, 20, 60, 1)",
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: "rgba(220, 20, 60, 1)",
                pointHoverBorderColor: "#fff",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              title: {
                display: true,
                text: chartTitle,
                color: "#222",
                font: { size: 16, weight: "bold" },
                padding: { top: 10, bottom: 15 },
              },
              tooltip: {
                backgroundColor: "rgba(30, 41, 59, 0.9)",
                titleColor: "#fff",
                bodyColor: "#f1f5f9",
                cornerRadius: 12,
                padding: 15,
                displayColors: false,
                borderColor: "rgba(220, 20, 60, 0.3)",
                borderWidth: 1,
                callbacks: {
                  label: (ctx) => "Rp " + ctx.raw.toLocaleString("id-ID"),
                },
              },
            },
            scales: {
              x: {
                ticks: { color: "#64748b", font: { size: 11, weight: "600" } },
                grid: { display: false },
              },
              y: {
                beginAtZero: true,
                ticks: {
                  color: "#64748b",
                  font: { size: 11, weight: "600" },
                  callback: (value) => "Rp " + value.toLocaleString("id-ID"),
                },
                grid: { color: "rgba(226, 232, 240, 0.6)", drawBorder: false },
              },
            },
            animation: { duration: 1000, easing: "easeOutQuart" },
          },
        });
      } catch (err) {
        console.error("❌ Gagal memuat grafik:", err);
      }
    }

    // 🔁 Ganti periode
    salesPeriodSelect.addEventListener("change", () => {
      const periode = salesPeriodSelect.value;
      if (periode === "custom") {
        dateRange.style.display = "flex";
      } else {
        dateRange.style.display = "none";
        loadChart(periode);
      }
    });

    // 📆 Klik "Terapkan" untuk custom range
    applyDate.addEventListener("click", () => {
      const start = startDate.value;
      const end = endDate.value;
      if (!start || !end) {
        alert("Silakan pilih tanggal awal dan akhir!");
        return;
      }
      loadChart("custom", start, end);
    });

    // 🚀 Muat default: hari ini
    loadChart("today");
  }

  // =====================
  // 🍽️ Chart Menu Terlaris
  // =====================
  const menuChartEl = document.getElementById("menuChart");
  const menuPeriodSelect = document.getElementById("menuPeriod");
  const topMenuNameEl = document.getElementById("topMenuName");
  const topMenuQtyEl = document.getElementById("topMenuQty");

  if (menuChartEl && menuPeriodSelect) {
    const ctxMenu = menuChartEl.getContext("2d");
    let menuChartInstance;

    async function loadMenuChart(periode = "today") {
      try {
        const res = await fetch(`get_menu_terlaris.php?periode=${periode}`);
        const result = await res.json();

        const labels = result.map((item) => item.nama_menu);
        const data = result.map((item) => item.total_terjual);

        if (result.length > 0) {
          topMenuNameEl.textContent = result[0].nama_menu;
          topMenuQtyEl.textContent = `${result[0].total_terjual} Porsi`;
        } else {
          topMenuNameEl.textContent = "-";
          topMenuQtyEl.textContent = "0 Porsi";
        }

        if (menuChartInstance) menuChartInstance.destroy();

        menuChartInstance = new Chart(ctxMenu, {
          type: "bar",
          data: {
            labels: labels,
            datasets: [
              {
                label: "Jumlah Terjual",
                data: data,
                backgroundColor: [
                  "#007bff",
                  "#22c55e",
                  "#facc15",
                  "#ef4444",
                  "#8b5cf6",
                ],
                borderRadius: 10,
                barThickness: 40,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false,
              },
              title: {
                display: true,
                text:
                  periode === "today"
                    ? "Menu Terlaris Hari Ini"
                    : periode === "week"
                    ? "Menu Terlaris Minggu Ini"
                    : "Menu Terlaris Bulan Ini",
                color: "#222",
                font: {
                  size: 16,
                  weight: "bold",
                },
              },
              tooltip: {
                backgroundColor: "rgba(0,0,0,0.85)",
                titleColor: "#fff",
                bodyColor: "#e5e7eb",
                cornerRadius: 8,
                padding: 10,
                callbacks: {
                  label: (ctx) => `${ctx.raw} Porsi`,
                },
              },
            },
            scales: {
              x: {
                ticks: {
                  color: "#374151",
                  font: { size: 12, weight: "500" },
                },
                grid: { display: false },
              },
              y: {
                beginAtZero: true,
                ticks: {
                  color: "#374151",
                  stepSize: 1,
                  callback: (v) => `${v} porsi`,
                },
                grid: { color: "rgba(0,0,0,0.05)" },
              },
            },
            animation: {
              duration: 1200,
              easing: "easeOutQuart",
            },
          },
        });
      } catch (err) {
        console.error("❌ Gagal memuat chart menu terlaris:", err);
      }
    }

    // 🔁 Ganti periode
    menuPeriodSelect.addEventListener("change", () => {
      loadMenuChart(menuPeriodSelect.value);
    });

    // 🚀 Muat awal
    loadMenuChart("today");
  }

  // =====================
  // ✅ Toggle Status Menu
  // =====================
  document.querySelectorAll(".btn-tandai").forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.getAttribute("data-id");

      fetch("update_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id_produk=" + encodeURIComponent(id),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            const card = btn.closest(".menu-card");
            if (data.status === 0) {
              card.classList.add("unavailable");
              btn.textContent = "Tandai Tersedia";
            } else {
              card.classList.remove("unavailable");
              btn.textContent = "Tandai Tidak Tersedia";
            }
            showAdminToast("Status menu berhasil diubah!", "success");
          } else {
            showAdminToast("Gagal update: " + data.error, "error");
          }
        })
        .catch((err) => {
          console.error("Error:", err);
          showAdminToast("Terjadi kesalahan saat menghubungi server.", "error");
        });
    });
  });

  // =====================
  // ✅ AJAX Update Status Pesanan / Pembayaran
  // =====================
  document.body.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-ajax");
    if (!btn) return;

    e.preventDefault();
    const id = btn.getAttribute("data-id");
    const status = btn.getAttribute("data-status");

    const parts = window.location.pathname.split("/").filter(Boolean);
    let paymentUrl = "payment_proses.php";
    if (parts.length >= 1) {
      paymentUrl = "/" + parts[0] + "/payment_proses.php";
    }

    fetch(paymentUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "id=" +
        encodeURIComponent(id) +
        "&status=" +
        encodeURIComponent(status),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          const row = btn.closest("tr");
          if (row) {
            if (status === "dibayar" || status === "sudah bayar") {
              const statusBayarCell = row.querySelector(".status-bayar");
              const aksiCell = row.querySelector(".aksi-bayar");
              if (statusBayarCell) {
                statusBayarCell.innerHTML =
                  "<span class='badge badge-success'>Sudah Bayar</span>";
              }
              if (aksiCell) {
                aksiCell.innerHTML =
                  "<span style='color: gray; font-weight: bold;'>Telah Dibayar</span>";
              }
            } else {
              const statusPesanCell = row.querySelector(".status-cell");
              if (statusPesanCell) {
                statusPesanCell.innerHTML =
                  "<span class='badge badge-info'>" + status + "</span>";
              }
            }
            showAdminToast("Status berhasil diperbarui!", "success");
          }
        } else {
          showAdminToast("Gagal update: " + (data.error || "Tidak diketahui"), "error");
        }
      })
      .catch((err) => {
        console.error("❌ Error AJAX:", err);
        showAdminToast("Terjadi kesalahan saat menghubungi server.", "error");
      });
  });

  // =====================
  // ✅ Auto Refresh Pesanan (Polling 5 detik)
  // =====================
  function loadPesanan() {
    fetch("pesanan.php?ajax=1")
      .then((res) => res.text())
      .then((html) => {
        const tbody = document.querySelector("#pesanan-table-body");
        if (tbody) {
          tbody.innerHTML = html;
        }
      })
      .catch((err) => console.error("❌ Error refresh pesanan:", err));
  }

  setInterval(loadPesanan, 5000);
});
