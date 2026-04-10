console.log("✅ MENU.JS FINAL FIX v9 (support id_varian) loaded");

// ===== Variabel Global =====
let cart = JSON.parse(localStorage.getItem("cart")) || [];
let selectedId = null;
let selectedQty = 0;
let selectedName = "";
let selectedPrice = 0;

// Simpan cart ke localStorage
function saveCart() {
  localStorage.setItem("cart", JSON.stringify(cart));
}

// ===== Fungsi baru: tutup semua popup =====
function closeAllPopups() {
  const popups = document.querySelectorAll(
    ".popup-overlay, #order-popup, #cart"
  );
  popups.forEach((p) => (p.style.display = "none"));
  resetAddMenuPopup();
}

// ===== POPUP Tambahkan menu =====
// signature: openPopup(name, hot, ice, image, variants=null, basePrice=0, id=null)
function openPopup(
  name,
  hot,
  ice,
  image,
  variants = null,
  basePrice = 0,
  id = null
) {
  closeAllPopups();

  // simpan id & default values (sanitize id masuk di confirm/add)
  selectedId = id;
  selectedQty = 0;
  selectedName = name;
  selectedPrice = basePrice || 0;

  const overlay = document.querySelector(".popup-overlay");
  if (overlay) overlay.style.display = "flex";

  const subtitle = document.getElementById("popup-subtitle");
  if (subtitle) subtitle.innerText = name;

  // gambar
  const popupImg = document.getElementById("popup-img");
  if (popupImg) {
    popupImg.src =
      image && image.trim() !== "" ? image : "image/menu/placeholder.png";
    popupImg.alt = name;
  }

  // harga default
  const priceEl = document.getElementById("popup-price");
  if (priceEl)
    priceEl.textContent = "Rp " + (selectedPrice || 0).toLocaleString("id-ID");

  // qty
  const qtyDisplay = document.getElementById("qty-display");
  if (qtyDisplay) qtyDisplay.innerText = selectedQty;

  // varian area
  const optionDiv = document.querySelector(".popup-option");
  if (!optionDiv) return;
  optionDiv.innerHTML = "";
  optionDiv.classList.remove("four-variants");
  optionDiv.style.display = "grid";

  if (variants && Array.isArray(variants) && variants.length > 0) {
    if (variants.length === 4) optionDiv.classList.add("four-variants");
    variants.forEach((v) => {
      optionDiv.innerHTML += `
        <label class="variant-label">
          <input type="radio" name="menu-option" 
                 value="${v.harga_varian}" 
                 data-variant="${v.nama_varian}" 
                 data-id="${v.id_varian}" />
          ${v.nama_varian}
        </label>
      `;
    });
  } else if (hot != null && ice != null) {
    optionDiv.innerHTML = `
      <label class="variant-label">
        <input type="radio" name="menu-option" value="${hot}" data-variant="Hot" data-id="hot" />
        Hot
      </label>
      <label class="variant-label">
        <input type="radio" name="menu-option" value="${ice}" data-variant="Ice" data-id="ice" />
        Ice
      </label>
    `;
  } else {
    optionDiv.style.display = "none";
  }

  // listener varian
  const radios = optionDiv.querySelectorAll("input[type=radio]");
  radios.forEach((radio) => {
    radio.addEventListener("change", function () {
      selectedPrice = parseInt(this.value, 10) || 0;
      const variant = this.dataset.variant || "";
      selectedName = name + (variant ? " - " + variant : "");
      const p = document.getElementById("popup-price");
      if (p) p.textContent = "Rp " + selectedPrice.toLocaleString("id-ID");
    });
  });
}

// overlay close
const popupOverlay = document.querySelector(".popup-overlay");
if (popupOverlay) {
  popupOverlay.addEventListener("click", (e) => {
    if (e.target.classList.contains("popup-overlay")) closeAllPopups();
  });
}

// close button
const closeBtn = document.querySelector(".popup-close");
if (closeBtn) closeBtn.addEventListener("click", closeAllPopups);

// ===== Qty Control =====
const btnMinus = document.getElementById("btn-minus");
if (btnMinus) {
  btnMinus.addEventListener("click", () => {
    if (selectedQty > 0) selectedQty--;
    const d = document.getElementById("qty-display");
    if (d) d.innerText = selectedQty;
  });
}
const btnPlus = document.getElementById("btn-plus");
if (btnPlus) {
  btnPlus.addEventListener("click", () => {
    selectedQty++;
    const d = document.getElementById("qty-display");
    if (d) d.innerText = selectedQty;
  });
}

// === Toast Function (Notifikasi Validasi) ===
function showToast(message) {
  let toast = document.getElementById("toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "toast";
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  toast.innerText = message;
  toast.classList.add("show");
  setTimeout(() => {
    toast.classList.remove("show");
  }, 3000);
}

// ===== Tambah ke Keranjang =====
const addToCartBtn = document.getElementById("add-to-cart-btn");
if (addToCartBtn) {
  addToCartBtn.addEventListener("click", () => {
    console.log("➡️ Sebelum tambah (raw cart):", cart);

    // sanitize selectedId (pastikan bukan array kosong)
    let pid = selectedId;
    if (Array.isArray(pid)) pid = pid[0] ?? null;
    pid = pid !== null && pid !== undefined && pid !== "" ? Number(pid) : null;

    const selectedOption = document.querySelector(
      '.popup-option input[type="radio"]:checked'
    );

    // jika ada varian tapi belum dipilih
    if (!selectedOption) {
      const optionInputs = document.querySelectorAll(
        ".popup-option input[type=radio]"
      );
      if (optionInputs.length > 0) {
        showToast(
          "Silakan pilih varian dulu sebelum menambahkan ke keranjang!"
        );
        return;
      }
    }

    let variantId = null;
    if (selectedOption) {
      selectedPrice = parseInt(selectedOption.value, 10) || 0;
      const variant = selectedOption.dataset.variant || "";
      variantId = selectedOption.dataset.id || null;

      if (variant)
        selectedName = selectedName.split(" - ")[0] + " - " + variant;
    }

    if (selectedQty > 0) {
      const noteEl = document.getElementById("note");
      const note = noteEl ? noteEl.value : "";

      // buat item lengkap — sertakan id_produk & id_varian untuk server
      const item = {
        id: pid,
        id_produk: pid,
        id_varian: variantId,
        name: selectedName,
        price: Number(selectedPrice) || 0,
        qty: Number(selectedQty) || 0,
        note: note || "",
      };

      // 🖼️ ambil gambar dari popup
      const popupImg = document.getElementById("popup-img");
      if (popupImg) item.img = popupImg.src;

      // gabung jika sama
      const found = cart.find(
        (c) =>
          (c.id === item.id ||
            (c.id == null && item.id == null && c.name === item.name)) &&
          c.name === item.name &&
          Number(c.price) === Number(item.price) &&
          (c.note || "") === (item.note || "") &&
          (c.id_varian || null) === (item.id_varian || null)
      );

      if (found) found.qty = Number(found.qty) + Number(item.qty);
      else cart.push(item);

      saveCart();
      renderCart();
      console.log("✅ Setelah tambah (sanitized cart):", cart);
      closeAllPopups();
    } else {
      showToast("Jumlah tidak boleh 0");
    }
  });
}

// ===== Reset Popup Tambah Menu =====
function resetAddMenuPopup() {
  selectedId = null;
  selectedQty = 0;
  selectedName = "";
  selectedPrice = 0;

  const qtyDisplay = document.getElementById("qty-display");
  if (qtyDisplay) qtyDisplay.innerText = 0;

  const noteInput = document.getElementById("note");
  if (noteInput) noteInput.value = "";

  const optionDiv = document.querySelector(".popup-option");
  if (optionDiv) {
    optionDiv.innerHTML = "";
    optionDiv.style.display = "none";
  }

  const priceText = document.getElementById("popup-price");
  if (priceText) priceText.textContent = "Rp 0";
}

// ===== CART =====
function toggleCart() {
  closeAllPopups();
  const cartDiv = document.getElementById("cart");
  if (!cartDiv) return;
  cartDiv.style.display = cartDiv.style.display === "flex" ? "none" : "flex";
}
function closeCart() {
  const c = document.getElementById("cart");
  if (c) c.style.display = "none";
}

function renderCart() {
  const cartItemsDiv = document.getElementById("cart-items");
  const cartCount = document.getElementById("cart-count");
  const cartTotal = document.getElementById("cart-total");

  if (!cartItemsDiv || !cartCount || !cartTotal) return;

  cartItemsDiv.innerHTML = "";
  let total = 0;
  let count = 0;

  cart.forEach((item, index) => {
    total += Number(item.price) * Number(item.qty);
    count += Number(item.qty);

    cartItemsDiv.innerHTML += `
      <div class="cart-item">
        <img src="${item.img || "image_menu/default.jpg"}" 
             alt="${item.name}" 
             class="cart-item-img">
        <div class="cart-item-left">
          <p class="cart-item-name">${item.name}</p>
          ${item.note ? `<p class="cart-item-note">(${item.note})</p>` : ""}
        </div>
        <div class="cart-item-right">
          <span class="cart-item-price">Rp${Number(item.price).toLocaleString(
            "id-ID"
          )}</span>
          <span class="cart-item-qty">x${Number(item.qty)}</span>
          <button onclick="removeFromCart(${index})">❌</button>
        </div>
      </div>
    `;
  });

  cartTotal.innerText = "Rp" + total.toLocaleString("id-ID");
  cartCount.innerText = count;
}

function removeFromCart(index) {
  cart.splice(index, 1);
  saveCart();
  renderCart();
}

// ===== NOTIFIKASI CUSTOM =====
function showNotification(message, type = "error") {
  const notif = document.createElement("div");
  notif.className = `custom-notification ${type}`;
  notif.innerText = message;
  document.body.appendChild(notif);

  // trigger animasi
  void notif.offsetWidth;
  notif.classList.add("show");

  // auto close
  setTimeout(() => {
    notif.classList.remove("show");
    setTimeout(() => {
      if (notif.parentNode) notif.parentNode.removeChild(notif);
    }, 300);
  }, 3000);
}

// ===== POPUP PESANAN ANDA =====
document.addEventListener("DOMContentLoaded", () => {
  renderCart();

  const checkoutBtn = document.querySelector(".checkout-btn");
  const orderPopup = document.getElementById("order-popup");
  const orderItems = document.getElementById("order-items");
  const orderTotal = document.getElementById("order-total");

  function renderOrderPopup() {
    if (!orderItems || !orderTotal) return;

    orderItems.innerHTML = "";
    let total = 0;

    cart.forEach((item, index) => {
      total += Number(item.price) * Number(item.qty);
      orderItems.innerHTML += `
      <div class="order-item">
        <img src="${item.img || "image_menu/default.jpg"}" 
             alt="${item.name}" 
             class="order-item-img">
        <div class="order-item-left">
          <p class="order-item-name">${item.name}</p>
          ${item.note ? `<p class="order-item-note">(${item.note})</p>` : ""}
        </div>
        <div class="order-item-right">
          <span class="order-item-price">
            Rp${Number(item.price).toLocaleString("id-ID")} x${Number(item.qty)}
          </span>
          <button onclick="removeFromCartPopup(${index})">❌</button>
        </div>
      </div>
    `;
    });

    orderTotal.innerText = "Rp" + total.toLocaleString("id-ID");
  }

  window.removeFromCartPopup = function (index) {
    cart.splice(index, 1);
    saveCart();
    renderCart();
    renderOrderPopup();
  };

  if (checkoutBtn) {
    checkoutBtn.addEventListener("click", () => {
      if (cart.length === 0) {
        showNotification(
          "Pilih menu terlebih dahulu sebelum checkout!",
          "warning"
        );
        return;
      }
      closeAllPopups();
      renderOrderPopup();
      if (orderPopup) orderPopup.style.display = "flex";
    });
  }

  window.closeOrderPopup = closeAllPopups;

  // ===== NOTIFIKASI CUSTOM =====
  function showNotification(message, type = "error") {
    const notif = document.createElement("div");
    notif.className = `custom-notification ${type}`;
    notif.innerText = message;

    document.body.appendChild(notif);

    setTimeout(() => {
      notif.classList.add("show");
    }, 10);

    setTimeout(() => {
      notif.classList.remove("show");
      setTimeout(() => notif.remove(), 300);
    }, 3000);
  }

  // ===== KONFIRMASI PESANAN =====
  window.confirmOrder = function () {
    console.log("🛒 Cart saat confirm:", cart);

    if (cart.length === 0) {
      showNotification("Pilih pesanan terlebih dahulu sebelum melanjutkan!");
      return;
    }

    const name =
      (document.getElementById("customer-name") &&
        document.getElementById("customer-name").value) ||
      "";
    const table =
      (document.getElementById("table-number") &&
        document.getElementById("table-number").value) ||
      "";

    if (name.trim() === "" || table.trim() === "") {
      showNotification("Isi Nama Pemesan dan Nomor Meja dulu ya!");
      return;
    }

    const total = cart.reduce(
      (sum, item) => sum + Number(item.price) * Number(item.qty),
      0
    );

    // buat payload dengan id_produk + id_varian
    const payload = cart.map((item) => {
      let pid = item.id;
      if (Array.isArray(pid)) pid = pid[0] ?? null;
      pid =
        pid !== null && pid !== undefined && pid !== "" ? Number(pid) : null;

      return {
        id_produk: pid,
        id: pid,
        id_varian: item.id_varian || null,
        name: String(item.name || ""),
        price: Number(item.price || 0),
        qty: Number(item.qty || 0),
        note: String(item.note || ""),
      };
    });

    console.log("📦 payload (to send via form):", payload);

    // isi form tersembunyi dan submit
    const form = document.getElementById("checkout-form");
    if (!form) {
      alert("Form checkout tidak ditemukan di halaman.");
      return;
    }

    const namaInput = document.getElementById("form-nama");
    const mejaInput = document.getElementById("form-meja");
    const totalInput = document.getElementById("form-total");
    const keranjangInput = document.getElementById("form-keranjang");

    if (!namaInput || !mejaInput || !totalInput || !keranjangInput) {
      alert("Elemen form tidak lengkap. Hubungi admin.");
      return;
    }

    namaInput.value = name;
    mejaInput.value = table;
    totalInput.value = total;
    keranjangInput.value = JSON.stringify(payload);

    // Kirim ke payment.php tapi hanya untuk tampilkan halaman pembayaran
    // Belum langsung masukkan ke database
    form.action = "payment.php";
    form.method = "POST";
    form.submit();
  };
});
