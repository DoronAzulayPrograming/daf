namespace Daf.Scaffolding;

class AppCss : ProjectFile
{
    public AppCss()
    {
        FilePath = Path.Combine("public", "app.css");
        Content = @"
main{
    margin: 0 50px;
}

a:visited{
    color:blue;
}
/* =========================
   Form validation
   ========================= */

/* is-invalid */
.is-invalid {
  border: 1px solid #dc3545;
  outline: 0;
}
.is-invalid:focus {
  border-color: #dc3545;
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

/* invalid-feedback */
.invalid-feedback {
  display: none;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}

/* Bootstrap behavior: show feedback when previous control is invalid */
.is-invalid ~ .invalid-feedback {
  display: block;
}

/* dispaly variants */
.d-block {
  display: block !important;
}

.d-none {
  display: none !important;
}


/* =========================
   Alerts
   ========================= */

.alert {
  position: relative;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  border: 1px solid transparent;
  border-radius: 0.375rem;
  font-size: 1rem;
  line-height: 1.5;
}

/* alert variants */
.alert-primary {
  color: #084298;
  background-color: #cfe2ff;
  border-color: #b6d4fe;
}

.alert-secondary {
  color: #41464b;
  background-color: #e2e3e5;
  border-color: #d3d6d8;
}

.alert-success {
  color: #0f5132;
  background-color: #d1e7dd;
  border-color: #a3cfbb;
}

.alert-info {
  color: #055160;
  background-color: #cff4fc;
  border-color: #b6effb;
}

.alert-warning {
  color: #664d03;
  background-color: #fff3cd;
  border-color: #ffecb5;
}

.alert-danger {
  color: #842029;
  background-color: #f8d7da;
  border-color: #f5c2c7;
}
        

/* =========================
   Progress Bar
   ========================= */
.corner-progress{
  position: fixed;
  right: 16px;
  bottom: 16px;
  width: 140px;
  height: 6px;
  border-radius: 999px;
  background: rgba(255,255,255,.18);
  overflow: hidden;
  z-index: 9999;
  pointer-events: none;
  backdrop-filter: blur(6px);
}

.corner-progress::before{
  content: "";
  position: absolute;
  inset: 0;
  width: 45%;
  border-radius: 999px;
  background: currentColor; /* set color via `color:` */
  animation: cornerProgress 0.5s ease-in-out infinite;
}

@keyframes cornerProgress{
  0%   { transform: translateX(-120%); opacity: .35; }
  50%  { opacity: 1; }
  100% { transform: translateX(260%); opacity: .35; }
}

/* example color */
.corner-progress{ color: #4ec9b0; }
        ";
    }
}
