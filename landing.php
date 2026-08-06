<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kaminar Fisioterapia | Agenda tu cita</title>
  <meta name="description" content="Fisioterapia especializada en Cajamarca. Agenda tu cita en Kaminar Fisioterapia y recupera tu movilidad con atención personalizada." />
  <!-- Meta Pixel Code -->
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '940337002136771');
  fbq('track', 'PageView');
  </script>
  <!-- End Meta Pixel Code -->
  <style>
    :root{
      --primary:#25b7d3;
      --primary-dark:#1594ad;
      --secondary:#0f2f3a;
      --light:#f5fcfe;
      --text:#1f2d33;
      --muted:#5f6f76;
      --white:#ffffff;
      --success:#1ea672;
      --shadow:0 12px 35px rgba(0,0,0,.10);
      --radius:22px;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:Arial, Helvetica, sans-serif;
      background:linear-gradient(180deg,#eefbfd 0%,#ffffff 45%,#f7fcfd 100%);
      color:var(--text);
      line-height:1.5;
    }

    a{text-decoration:none}
    img{max-width:100%;display:block}

    .container{
      width:min(1120px,92%);
      margin:0 auto;
    }

    .topbar{
      padding:18px 0;
      background:rgba(255,255,255,.75);
      backdrop-filter: blur(8px);
      position:sticky;
      top:0;
      z-index:100;
      border-bottom:1px solid rgba(0,0,0,.05);
    }

    .topbar-wrap{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:16px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:12px;
      font-weight:700;
      color:var(--secondary);
    }

    .brand-logo{
      width:52px;
      height:52px;
      border-radius:50%;
      overflow:hidden;
      border:1px solid rgba(21,148,173,.25);
      box-shadow:var(--shadow);
      background:#fff;
      display:grid;
      place-items:center;
    }

    .brand-logo img{
      width:100%;
      height:100%;
      object-fit:cover;
    }

    .nav-cta{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:14px 22px;
      border-radius:999px;
      font-weight:700;
      transition:.2s ease;
      border:none;
      cursor:pointer;
      text-align:center;
    }

    .btn-primary{
      background:var(--primary);
      color:#fff;
      box-shadow:0 10px 25px rgba(37,183,211,.28);
    }

    .btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px)}
    .btn-outline{
      background:#fff;
      color:var(--secondary);
      border:1px solid rgba(15,47,58,.14);
    }

    .hero{
      padding:62px 0 32px;
    }

    .hero-grid{
      display:grid;
      grid-template-columns:1.1fr .9fr;
      gap:34px;
      align-items:center;
    }

    .badge{
      display:inline-block;
      background:#dff8fd;
      color:var(--primary-dark);
      padding:9px 15px;
      border-radius:999px;
      font-weight:700;
      font-size:.92rem;
      margin-bottom:18px;
    }

    h1{
      font-size:clamp(2.1rem,5vw,4rem);
      line-height:1.08;
      color:var(--secondary);
      margin-bottom:18px;
    }

    .hero p{
      font-size:1.08rem;
      color:var(--muted);
      margin-bottom:18px;
      max-width:640px;
    }

    .offer-box{
      background:#fff;
      border:1px solid rgba(37,183,211,.18);
      box-shadow:var(--shadow);
      border-radius:18px;
      padding:18px 20px;
      margin:22px 0;
    }

    .offer-box strong{
      color:var(--secondary);
      font-size:1.15rem;
      display:block;
      margin-bottom:6px;
    }

    .hero-actions{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
      margin-top:24px;
    }

    .hero-card{
      background:#fff;
      border-radius:var(--radius);
      padding:24px;
      box-shadow:var(--shadow);
      border:1px solid rgba(0,0,0,.04);
    }

    .hero-photo{
      min-height:440px;
      border-radius:20px;
      background:
        linear-gradient(rgba(15,47,58,.18), rgba(15,47,58,.18)),
        url('/uploads/landing/dolor-lumbar-campana.png') center/cover no-repeat;
      background-color:#d8e1e5;
      position:relative;
      overflow:hidden;
    }

    .floating-card{
      position:absolute;
      left:20px;
      right:20px;
      bottom:20px;
      background:rgba(255,255,255,.95);
      border-radius:18px;
      padding:16px;
      box-shadow:var(--shadow);
    }

    .floating-card h3{
      color:var(--secondary);
      margin-bottom:6px;
      font-size:1rem;
    }

    .floating-card p{
      color:var(--muted);
      font-size:.95rem;
      margin:0;
    }

    .stats{
      padding:28px 0 18px;
    }

    .stats-grid{
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:18px;
    }

    .stat{
      background:#fff;
      border-radius:18px;
      padding:24px 18px;
      text-align:center;
      box-shadow:var(--shadow);
    }

    .stat strong{
      display:block;
      font-size:1.8rem;
      color:var(--primary-dark);
    }

    .section{
      padding:54px 0;
    }

    .section-title{
      text-align:center;
      margin-bottom:30px;
    }

    .section-title h2{
      color:var(--secondary);
      font-size:clamp(1.7rem,3vw,2.4rem);
      margin-bottom:10px;
    }

    .section-title p{
      color:var(--muted);
      max-width:760px;
      margin:0 auto;
    }

    .services{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:22px;
    }

    .service-card,.trust-card,.step,.contact-card{
      background:#fff;
      border-radius:22px;
      padding:24px;
      box-shadow:var(--shadow);
    }

    .service-card h3,.trust-card h3,.step h3,.contact-card h3{
      color:var(--secondary);
      margin-bottom:10px;
    }

    .service-card p,.trust-card p,.step p,.contact-card p{
      color:var(--muted);
    }

    .icon{
      width:52px;
      height:52px;
      border-radius:16px;
      background:linear-gradient(135deg,#dff8fd,#edfdfd);
      display:grid;
      place-items:center;
      margin-bottom:14px;
      font-size:24px;
    }

    .trust-grid{
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:20px;
    }

    .steps{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:20px;
    }

    .step-number{
      width:42px;
      height:42px;
      border-radius:50%;
      background:var(--primary);
      color:#fff;
      display:grid;
      place-items:center;
      font-weight:700;
      margin-bottom:14px;
    }

    .cta-band{
      background:linear-gradient(135deg,var(--primary),#79deeb);
      color:#fff;
      border-radius:28px;
      padding:38px 28px;
      display:grid;
      grid-template-columns:1.2fr .8fr;
      gap:20px;
      align-items:center;
      box-shadow:var(--shadow);
    }

    .cta-band h2{
      font-size:clamp(1.7rem,3vw,2.5rem);
      margin-bottom:10px;
    }

    .cta-band p{
      opacity:.97;
    }

    .cta-actions{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }

    .cta-band .btn-primary{
      background:#fff;
      color:var(--secondary);
      box-shadow:none;
    }

    .cta-band .btn-outline{
      background:transparent;
      border:1px solid rgba(255,255,255,.55);
      color:#fff;
    }

    .contact-grid{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:20px;
    }

    footer{
      padding:26px 0 44px;
      text-align:center;
      color:var(--muted);
      font-size:.96rem;
    }

    .list{
      list-style:none;
      display:grid;
      gap:10px;
      margin-top:12px;
    }

    .list li{
      color:var(--muted);
      padding-left:26px;
      position:relative;
    }

    .list li::before{
      content:'✔';
      position:absolute;
      left:0;
      color:var(--success);
      font-weight:bold;
    }

    @media (max-width: 980px){
      .hero-grid,.cta-band,.services,.trust-grid,.steps,.contact-grid,.stats-grid{
        grid-template-columns:1fr 1fr;
      }
      .cta-actions{justify-content:flex-start}
    }

    @media (max-width: 720px){
      .hero{padding-top:36px}
      .hero-grid,.cta-band,.services,.trust-grid,.steps,.contact-grid,.stats-grid{
        grid-template-columns:1fr;
      }
      .topbar-wrap{
        flex-direction:column;
        align-items:flex-start;
      }
      .hero-photo{min-height:340px}
      .nav-cta,.hero-actions,.cta-actions{width:100%}
      .nav-cta a,.hero-actions a,.cta-actions a{flex:1}
      .btn{width:100%}
    }
  </style>
</head>
<body>
  <noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=940337002136771&ev=PageView&noscript=1"
  /></noscript>

  <header class="topbar">
    <div class="container topbar-wrap">
      <div class="brand">
        <div class="brand-logo"><img src="uploads/branding/logo-fisio.png" alt="Kaminar Fisio"></div>
        <div>
          <div>Kaminar Fisioterapia</div>
          <small style="color:#5f6f76;font-weight:600;">Cajamarca</small>
        </div>
      </div>
      <div class="nav-cta">
        <a class="btn btn-outline" data-track="whatsapp" href="https://wa.me/51921553520?text=Hola%20quiero%20informaci%C3%B3n%20sobre%20fisioterapia">WhatsApp</a>
        <a class="btn btn-primary" data-track="agendar" href="https://app3.kaminar.pe/ingreso.php">Agendar cita</a>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="container hero-grid">
        <div>
          <span class="badge">Atención personalizada en fisioterapia y rehabilitación</span>
          <h1>Recupera tu movilidad y vuelve a sentirte bien</h1>
          <p>
            En Kaminar Fisioterapia te ayudamos con dolor de espalda, rodilla, hombro, secuelas postoperatorias y lesiones musculoesqueléticas mediante un tratamiento personalizado y seguimiento real.
          </p>

          <div class="offer-box">
            <strong>Primera evaluación fisioterapéutica</strong>
            <span>Agenda hoy tu evaluación y recibe un plan inicial personalizado según tu dolor, movilidad y objetivo. Cupos limitados por horario.</span>
          </div>

          <ul class="list">
            <li>Tratamiento personalizado según tu dolor o lesión</li>
            <li>Atención profesional y seguimiento de tu evolución</li>
            <li>Horarios prácticos para que puedas atenderte</li>
          </ul>

          <div class="hero-actions">
            <a class="btn btn-primary" data-track="agendar" href="https://app3.kaminar.pe/ingreso.php">AGENDAR MI CITA AHORA</a>
            <a class="btn btn-outline" data-track="whatsapp" href="https://wa.me/51921553520?text=Hola%2C%20quiero%20consultar%20por%20una%20cita%20de%20fisioterapia">ESCRIBIR POR WHATSAPP</a>
          </div>
        </div>

        <div class="hero-card">
          <div class="hero-photo">
            <div class="floating-card">
              <h3>Agenda online o por WhatsApp</h3>
              <p>Elige la opción más cómoda para ti y consulta disponibilidad inmediata.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="stats">
      <div class="container stats-grid">
        <div class="stat"><strong>1 hora</strong><span>por sesión</span></div>
        <div class="stat"><strong>Atención</strong><span>personalizada</span></div>
        <div class="stat"><strong>Agenda</strong><span>rápida</span></div>
        <div class="stat"><strong>Seguimiento</strong><span>real</span></div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-title">
          <h2>¿En qué podemos ayudarte?</h2>
          <p>Ideal para pacientes que buscan aliviar el dolor, recuperar movimiento y mejorar su funcionalidad con un tratamiento guiado.</p>
        </div>

        <div class="services">
          <article class="service-card">
            <div class="icon">🦴</div>
            <h3>Dolor articular</h3>
            <p>Rodilla, hombro, tobillo, cadera y otras molestias que limitan tus actividades diarias.</p>
          </article>

          <article class="service-card">
            <div class="icon">💪</div>
            <h3>Lesiones musculares</h3>
            <p>Rehabilitación enfocada en reducir dolor, mejorar fuerza y recuperar movimiento.</p>
          </article>

          <article class="service-card">
            <div class="icon">🚶</div>
            <h3>Postoperatorios y fracturas</h3>
            <p>Acompañamiento terapéutico para avanzar paso a paso en tu recuperación.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section" style="background:#f3fbfd;">
      <div class="container">
        <div class="section-title">
          <h2>¿Por qué elegir Kaminar Fisioterapia?</h2>
          <p>No se trata solo de usar máquinas, sino de evaluar bien, planificar el tratamiento y hacer seguimiento a cada paciente.</p>
        </div>

        <div class="trust-grid">
          <article class="trust-card">
            <div class="icon">✅</div>
            <h3>Evaluación personalizada</h3>
            <p>Analizamos tu caso para orientarte mejor desde el inicio.</p>
          </article>
          <article class="trust-card">
            <div class="icon">📋</div>
            <h3>Plan de tratamiento</h3>
            <p>Cada paciente necesita un enfoque distinto según su dolor y limitación.</p>
          </article>
          <article class="trust-card">
            <div class="icon">⚙️</div>
            <h3>Equipos de apoyo</h3>
            <p>Complementamos la terapia con recursos físicos según lo que necesites.</p>
          </article>
          <article class="trust-card">
            <div class="icon">📈</div>
            <h3>Seguimiento real</h3>
            <p>Buscamos que notes progreso y avances funcionales durante tus sesiones.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-title">
          <h2>Agenda tu cita en 3 pasos</h2>
          <p>Hazlo online o, si prefieres, escríbenos por WhatsApp y te ayudamos.</p>
        </div>

        <div class="steps">
          <article class="step">
            <div class="step-number">1</div>
            <h3>Ingresa a la agenda</h3>
            <p>Haz clic en el botón de agendar para entrar al sistema.</p>
          </article>
          <article class="step">
            <div class="step-number">2</div>
            <h3>Elige tu horario</h3>
            <p>Selecciona la fecha y hora que mejor se adapte a ti.</p>
          </article>
          <article class="step">
            <div class="step-number">3</div>
            <h3>Confirma tu cita</h3>
            <p>Completa tus datos y deja reservada tu atención.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="cta-band">
          <div>
            <h2>Empieza tu recuperación hoy</h2>
            <p>Si tienes dolor o dificultad para moverte, agenda tu cita ahora y revisa la disponibilidad.</p>
          </div>
          <div class="cta-actions">
            <a class="btn btn-primary" data-track="agendar" href="https://app3.kaminar.pe/ingreso.php">Agendar online</a>
            <a class="btn btn-outline" data-track="whatsapp" href="https://wa.me/51921553520?text=Hola%2C%20quiero%20agendar%20una%20cita%20de%20fisioterapia">WhatsApp</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-title">
          <h2>Información de contacto</h2>
          <p>Personaliza estos datos antes de subir la página, si deseas.</p>
        </div>

        <div class="contact-grid">
          <article class="contact-card">
            <h3>Dirección</h3>
            <p>Jr. del Comercio 142, Cajamarca</p>
          </article>
          <article class="contact-card">
            <h3>WhatsApp</h3>
            <p>921 553 520</p>
          </article>
          <article class="contact-card">
            <h3>Horario</h3>
            <p>Lunes a sábado<br>8:00 am - 1:00 pm / 2:30 pm - 7:30 pm</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="container">
      Kaminar Fisioterapia - Recuperación con atención personalizada
    </div>
  </footer>
  <script>
    document.querySelectorAll('[data-track="agendar"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (typeof fbq === 'function') {
          fbq('track', 'Schedule');
          fbq('trackCustom', 'ClickAgendar');
        }
      });
    });

    document.querySelectorAll('[data-track="whatsapp"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (typeof fbq === 'function') {
          fbq('track', 'Lead');
          fbq('trackCustom', 'ClickWhatsApp');
        }
      });
    });
  </script>
</body>
</html>
