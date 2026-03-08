<?php
/**
 * @daf-summary Provides core Daf SPA navigation runtime, script loading, DOM morphing, and form submission hooks.
 */
?>

<script>
    // URL of the script to be loaded
    var scriptURL = "https://unpkg.com/morphdom@2.3.3/dist/morphdom-umd.min.js";
    loadScript(scriptURL);

    function loadScript(url) {
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = url;
        document.head.appendChild(script);
    }

    class DafEventObj {
        constructor(){
            this.isPreventDefault = false;
            this.isStopPropagation = false;
        }
        preventDefault(){ this.isPreventDefault = true; }
        stopPropagation(){ this.isStopPropagation = true; }
    }

    class DafProgress {
        constructor(){
            this.loader = null;
        }
        show = ()=>{
            const oldLoader = document.getElementById("daf-page-progress")
            if(oldLoader) oldLoader.remove();

            this.loader = document.createElement("div")
            this.loader.id = "daf-page-progress"
            this.loader.className = "position-absolute bottom-0 right-0 w-25"
            this.loader.innerHTML = `
            <div class="progress rounded-end-0" role="progressbar" aria-label="Animated striped loader" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
            </div>`;
            document.body.appendChild(this.loader)
        }
        hide(){
            if (this.loader) this.loader.remove();
        }
    }

    class Daf {
        constructor(){
            this.events = {
                onNavigateStart:[],
                onNavigateEnd:[]
            };


            Event.prototype.Daf = new DafEventObj();

            // Back/Forward: browser restores scroll (auto) → we do NOT interfere
            window.addEventListener('popstate', () => {
                this.#fetchContent(window.location.href);
            });

            this.init();
        }

        init(){
            this.#initATags();
            this.#initFromTags();
        }

        addEventListener(type, callback){
            if(type === 'navigateStart') this.events.onNavigateStart.push(callback)
            else if(type === 'navigateEnd') this.events.onNavigateEnd.push(callback)
            else throw new Error('Invalid event type')
        }

        removeEventListener(type, callback){
            const arr =
                type === 'navigateStart' ? this.events.onNavigateStart :
                type === 'navigateEnd' ? this.events.onNavigateEnd :
                null;

            if (!arr) throw new Error('Invalid event type');

            for (let i = 0; i < arr.length; i++) {
                if(arr[i].toString() === callback.toString()){
                    arr.splice(i, 1);
                    break;
                }
            }
        }

        // options:
        //   preserveScroll: true/false   (default false = browser/SPA default)
        //   scrollBehavior: "auto"|"smooth" (default "auto")
        navigate(url, options = {}) {
            const urlLink = new URL(url);
            if (urlLink.href === window.location.href) return;

            this.events.onNavigateStart.forEach(e => e(urlLink.href));

            // ✅ optional: force top (if you want "not preserve scroll")
            if (options.preserveScroll === false) {
                const behavior = options.scrollBehavior ?? "auto";
                window.scrollTo({ left: 0, top: 0, behavior });
            }

            history.pushState({}, '', urlLink.href);

            this.#fetchContent(urlLink.href);

            this.events.onNavigateEnd.forEach(e => e(urlLink.href));
        }

        #fetchContent(url) {
            if (new URL(url).origin === window.location.origin) {
                const progress = new DafProgress();
                progress.show();

                fetch(url, { method: 'GET' })
                    .then(r => r.text())
                    .then(this.#updatePage)
                    .catch(err => console.error('Error loading the page: ', err))
                    .finally(()=> progress.hide());
            } else {
                window.location.href = url;
            }
        }

        renderHtml = (html)=>{
            this.#updatePage(html);
        }

        #updatePage = (html)=>{
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const runableScripts = doc.querySelectorAll("div[data-daf-scripts] script");
            let scripts = [];
            runableScripts.forEach(s => scripts.push(s.textContent));

            doc.querySelector('div[data-daf-scripts]')?.remove();

            morphdom(document.querySelector("html"), doc.querySelector('html'));
            this.init();

            scripts.forEach(code=>{
                let script = document.createElement('script');
                script.type = 'text/javascript';
                script.textContent = code;
                document.body.appendChild(script);
            });
        }

        #initATags() {
            document.querySelectorAll("a:not([data-daf-ignore])").forEach(a=>{
                a.removeEventListener('click', this.#aTagClick);
                a.addEventListener('click', this.#aTagClick);
            });
        }

        #aTagClick = (e) => {
            if (e.Daf) {
                if (e.Daf.isPreventDefault === true) return;
                if (e.Daf.isStopPropagation === true) e.stopPropagation();
            }

            e.preventDefault();

            const a = e.currentTarget;
            const url = a.href;
            if (!URL.canParse(url)) return;

            // ✅ optional per-link reset
            const preserveScroll = a.getAttribute("data-daf-preserve-scroll") === "true";
            const scrollBehavior = a.getAttribute("data-daf-scroll-behavior") ?? undefined;

            this.navigate(url, { preserveScroll, scrollBehavior });
        }

        #initFromTags() {
            document.querySelectorAll("form:not([data-daf-ignore])").forEach(f=>{
                f.removeEventListener('submit', this.#onFromSubmit);
                f.addEventListener('submit', this.#onFromSubmit);
            });
        }

        #onFromSubmit = (e) => {
            const form = e.target;
            const url = form.action;

            if (new URL(url).origin === window.location.origin){
                e.preventDefault();

                // ✅ optional per-link reset
                const preserveScroll = form.getAttribute("data-daf-preserve-scroll") === "true";
                const scrollBehavior = form.getAttribute("data-daf-scroll-behavior") ?? undefined;
                    
                if (preserveScroll === false) {
                    const behavior = scrollBehavior ?? "auto";
                    window.scrollTo({ left: 0, top: 0, behavior });
                }
                
                this.#submitFormFetch(form);
            }
        }

        #collectFormData(form) {
            return new FormData(form);
        }

        #submitFormFetch(form) {
            const url = form.action;
            const method = form.method || "GET";
            const data = this.#collectFormData(form);

            this.events.onNavigateStart.forEach(e => e(url));

            return fetch(url, { method, body: data })
                .then(response => {
                    history.pushState({}, '', response.url);
                    return response.text();
                })
                .then(this.#updatePage)
                .catch(error => console.error('Error loading the page: ', error))
                .finally(() => {
                    this.events.onNavigateEnd.forEach(e => e(url));
                });
        }

        // options:
        //   resetScroll: true/false
        //   scrollBehavior: "auto"|"smooth"
        submitForm(formOrId, options = {}) {
            const form = typeof formOrId === "string"
                ? document.getElementById(formOrId)
                : formOrId;

            if (!form) return;

            const url = form.action;
            if (new URL(url).origin !== window.location.origin) {
                form.submit();
                return;
            }

            if (options.preserveScroll === false) {
                const behavior = options.scrollBehavior ?? "auto";
                window.scrollTo({ left: 0, top: 0, behavior });
            }

            return this.#submitFormFetch(form);
        }
    }

    window.Daf = new Daf();
</script>


<!-- 
<acript>
  class DafEventObj {
    constructor() {
      this.isPreventDefault = false;
      this.isStopPropagation = false;
    }
    preventDefault() { this.isPreventDefault = true; }
    stopPropagation() { this.isStopPropagation = true; }
  }

  function installDafEventOnWindow(win) {
    try {
      if (win.__dafEventInstalled) return;
      win.__dafEventInstalled = true;

      Object.defineProperty(win.Event.prototype, "Daf", {
        configurable: true,
        get: function () {
          if (!this.__dafEvt) this.__dafEvt = new DafEventObj();
          return this.__dafEvt;
        }
      });
    } catch (_) { }
  }

  class DafProgress {
    constructor() { this.loader = null; }
    show = () => {
      const old = document.getElementById("daf-page-progress");
      if (old) old.remove();

      this.loader = document.createElement("div");
      this.loader.id = "daf-page-progress";
      this.loader.className = "position-absolute bottom-0 end-0 w-25";
      this.loader.innerHTML = `
        <div class="progress rounded-end-0" role="progressbar" aria-label="Loading" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
        </div>`;
      document.body.appendChild(this.loader);
    };
    hide() { if (this.loader) this.loader.remove(); }
  }

  class Daf {
    constructor(options = {}) {
      this.events = { onNavigateStart: [], onNavigateEnd: [] };

      installDafEventOnWindow(window);

      this.iframeId = options.iframeId || "daf-app-frame";
      this.mountSelector = options.mountSelector || "body";
      this.keepOriginalOnInit = true; // phase 1

      this.frame = null;

      // scroll memory (SPA feel)
      this.scrollByUrl = new Map(); // url -> {x,y}

      // PHASE 1: init current real page (no iframe yet)
      this.init(document);

      window.addEventListener("popstate", () => {
        // restore on back/forward
        this.#fetchContent(window.location.href, /*isHistory*/ true);
      });
    }

    addEventListener(type, cb) {
      if (type === "navigateStart") this.events.onNavigateStart.push(cb);
      else if (type === "navigateEnd") this.events.onNavigateEnd.push(cb);
      else throw new Error("Invalid event type");
    }

    removeEventListener(type, cb) {
      const arr =
        type === "navigateStart" ? this.events.onNavigateStart :
          type === "navigateEnd" ? this.events.onNavigateEnd : null;
      if (!arr) throw new Error("Invalid event type");
      const i = arr.findIndex(x => x.toString() === cb.toString());
      if (i >= 0) arr.splice(i, 1);
    }

    // phase 1 + phase 2 init (document can be main doc OR iframe doc)
    init(doc) {
      this.#initATags(doc);
      this.#initFormTags(doc);
    }

    navigate(url) {
      const u = new URL(url, window.location.href);
      if (u.href === window.location.href) return;

      // remember current scroll before leaving (main doc or iframe)
      this.#saveScroll(window.location.href);

      this.events.onNavigateStart.forEach(fn => fn(u.href));
      history.pushState({}, "", u.href);
      this.#fetchContent(u.href, /*isHistory*/ false);
    }

    renderHtml(html, baseUrl = window.location.href) {
      // render into iframe and init its tags
      this.#ensureIframeAndReplaceBody();
      this.#setIframeSrcdoc(html, baseUrl);
    }

    submitForm(formOrId) {
      const doc = this.#activeDoc();
      if (!doc) return;

      const form = (typeof formOrId === "string")
        ? doc.getElementById(formOrId)
        : formOrId;

      if (!form) return;

      const action = form.action || window.location.href;
      const url = new URL(action, window.location.href);

      if (url.origin !== window.location.origin) {
        form.submit();
        return;
      }

      return this.#submitFormFetch(form);
    }

    // ---------------- private ----------------
    #createIframe(id) {
      const iframe = document.createElement("iframe");
      iframe.id = id;
      iframe.style.position = "absolute";
      iframe.style.inset = "0";
      iframe.style.width = "100%";
      iframe.style.height = "100%";
      iframe.style.border = "0";
      iframe.style.opacity = "0";
      iframe.style.pointerEvents = "none";
      iframe.style.transition = "none";
      return iframe;
    }

    #activeDoc() {
      // if iframe exists use it, else main doc (phase 1)
      if (this.frame && this.frame.contentDocument) return this.frame.contentDocument;
      return document;
    }

    #saveScroll(url) {
      // if iframe active, save iframe scroll, else window scroll
      if (this.frame && this.frame.contentWindow) {
        const w = this.frame.contentWindow;
        this.scrollByUrl.set(url, { x: w.scrollX || 0, y: w.scrollY || 0 });
      } else {
        this.scrollByUrl.set(url, { x: window.scrollX || 0, y: window.scrollY || 0 });
      }
    }

    #restoreScroll(url) {
      const pos = this.scrollByUrl.get(url);
      if (!pos) return;

      // if iframe active, restore inside it, else restore window
      if (this.frame && this.frame.contentWindow) {
        this.frame.contentWindow.scrollTo(pos.x, pos.y);
      } else {
        window.scrollTo(pos.x, pos.y);
      }
    }

    #ensureIframeAndReplaceBody() {
      if (this.frame) return;

      const saved = { x: window.scrollX, y: window.scrollY };

      // lock scrollbar to prevent layout shift blink
      const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
      document.documentElement.style.overflow = "hidden";
      if (scrollbarWidth > 0) {
        document.documentElement.style.paddingRight = `${scrollbarWidth}px`;
      }

      // 1) wrap current body content (keep it visible until iframe is ready)
      if (!document.getElementById("daf-original-host")) {
        const original = document.createElement("div");
        original.id = "daf-original-host";
        original.style.display = "contents";

        // move all current nodes into original host
        while (document.body.firstChild) {
          original.appendChild(document.body.firstChild);
        }

        document.body.appendChild(original);
      }

      // 2) overlay host on top (but don't blank screen)
      const host = document.createElement("div");
      host.id = "daf-frame-host";
      host.style.position = "fixed";
      host.style.inset = "0";
      host.style.width = "100%";
      host.style.height = "100%";
      host.style.overflow = "hidden";
      host.style.zIndex = "2147483647"; // ensure on top
      host.style.opacity = "0";
      host.style.pointerEvents = "none";
      host.style.transition = "opacity 120ms ease";

      const bg =
        getComputedStyle(document.body).backgroundColor ||
        getComputedStyle(document.documentElement).backgroundColor ||
        "#0f0f10";

      host.style.backgroundColor = bg;
      host.style.background = bg;

      this.frame = this.#createIframe("daf-frame-a");
      this.nextFrame = this.#createIframe("daf-frame-b");

      this.frame.style.backgroundColor = bg;
      this.frame.style.background = bg;
      this.frame.style.colorScheme = "dark";

      this.nextFrame.style.backgroundColor = bg;
      this.nextFrame.style.background = bg;
      this.nextFrame.style.colorScheme = "dark";

      document.body.appendChild(host);
      host.append(this.frame, this.nextFrame);

      // keep both hidden until we have content
      this.frame.style.opacity = "0";
      this.frame.style.pointerEvents = "none";

      this.nextFrame.style.opacity = "0";
      this.nextFrame.style.pointerEvents = "none";

      this._navToken = 0;

      requestAnimationFrame(() => window.scrollTo(saved.x, saved.y));
    }

        #setIframeSrcdoc(html, baseUrl) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      let base = doc.querySelector("head base");
      if (!base) {
        base = doc.createElement("base");
        doc.head.prepend(base);
      }
      base.setAttribute("href", baseUrl);

      // Hide document until CSS is ready to prevent blink
      const hideStyle = doc.createElement("style");
      hideStyle.textContent = "html{visibility:hidden}";
      doc.head.prepend(hideStyle);

      const incoming = this.nextFrame;
      const outgoing = this.frame;
      const token = ++this._navToken;

      const waitForStyles = (d) => {
        const links = Array.from(d.querySelectorAll('link[rel="stylesheet"]'));
        if (links.length === 0) return Promise.resolve();

        return Promise.all(
          links.map(l => {
            // if sheet already loaded, resolve immediately
            if (l.sheet) return Promise.resolve();
            return new Promise(res => {
              l.addEventListener("load", res, { once: true });
              l.addEventListener("error", res, { once: true });
            });
          })
        );
      };

      const finalizeSwap = () => {
        if (token !== this._navToken) return;

        try { installDafEventOnWindow(incoming.contentWindow); } catch {}
        try { this.init(incoming.contentDocument); } catch {}

        this.#restoreScroll(window.location.href);

        // swap refs
        this.frame = incoming;
        this.nextFrame = outgoing;

        incoming.style.opacity = "1";
        incoming.style.pointerEvents = "auto";

        outgoing.style.opacity = "0";
        outgoing.style.pointerEvents = "none";

        const original = document.getElementById("daf-original-host");
        if (original) original.remove();

        const host = document.getElementById("daf-frame-host");
        if (host) {
          host.style.opacity = "1";
          host.style.pointerEvents = "auto";
        }

        // optional cleanup
        setTimeout(() => {
          if (this.nextFrame === outgoing) outgoing.srcdoc = "";
        }, 250);
      };

      const onLoad = async () => {
        if (token !== this._navToken) return;

        try {
          await waitForStyles(incoming.contentDocument);

          const fonts = incoming.contentDocument && incoming.contentDocument.fonts;
          if (fonts && fonts.ready) await fonts.ready;

          // reveal after CSS + fonts are ready
          const style = incoming.contentDocument.querySelector("style");
          if (style && style.textContent.includes("visibility:hidden")) {
            style.remove();
          }
        } catch (_) {}

        finalizeSwap()
      };

      incoming.addEventListener("load", onLoad, { once: true });

      // write srcdoc (load will fire)
      incoming.srcdoc = "<!doctype html>\n" + doc.documentElement.outerHTML;
    }


    #fetchContent(url, isHistory) {
      const u = new URL(url, window.location.href);
      if (u.origin !== window.location.origin) {
        window.location.href = u.href;
        return;
      }

      const progress = new DafProgress();
      progress.show();

      fetch(u.href, { method: "GET", headers:{'daf-js': 'just-placeholder'} })
        .then(r => r.text().then(t => ({ text: t, finalUrl: r.url })))
        .then(({ text, finalUrl }) => {
          // phase 2 on first navigation
          this.#ensureIframeAndReplaceBody();

          // if server redirected, keep history in sync (but avoid double push on popstate)
          if (!isHistory && finalUrl && finalUrl !== window.location.href) {
            history.replaceState({}, "", finalUrl);
          }

          this.#setIframeSrcdoc(text, finalUrl || u.href);
        })
        .catch(err => console.error("Error loading the page:", err))
        .finally(() => {
          progress.hide();
          this.events.onNavigateEnd.forEach(fn => fn(u.href));
        });
    }

    // -------- A TAGS --------
    #initATags(doc) {
      doc.querySelectorAll("a:not([data-daf-ignore])").forEach(a => {
        a.removeEventListener("click", this.#aTagClick, true);
        a.addEventListener("click", this.#aTagClick, true);
      });
    }

    #aTagClick = (e) => {
      // allow open in new tab, middle click, modifiers
      if (e.defaultPrevented) return;
      if (e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

      if (e.Daf) {
        if (e.Daf.isPreventDefault) return;
        if (e.Daf.isStopPropagation) e.stopPropagation();
      }

      const a = e.currentTarget;
      if (!a || !a.href) return;

      if (a.hasAttribute("download")) return;
      const target = (a.getAttribute("target") || "").toLowerCase();
      if (target && target !== "_self") return;

      if (!URL.canParse(a.href)) return;

      const url = new URL(a.href, window.location.href);
      if (url.origin !== window.location.origin) return;

      const sameDoc =
        url.pathname === window.location.pathname &&
        url.search === window.location.search;

      if (sameDoc) return;

      e.preventDefault();
      this.navigate(url.href);
    };

    // -------- FORMS --------
    #initFormTags(doc) {
      doc.querySelectorAll("form:not([data-daf-ignore])").forEach(f => {
        f.removeEventListener("submit", this.#onFormSubmit, true);
        f.addEventListener("submit", this.#onFormSubmit, true);
      });
    }

    #onFormSubmit = (e) => {
      const form = e.target;
      if (!form) return;

      const action = form.action || window.location.href;
      const url = new URL(action, window.location.href);

      if (url.origin !== window.location.origin) return;

      e.preventDefault();
      this.#submitFormFetch(form);
    };

    #submitFormFetch(form) {
      // remember current scroll before leaving
      this.#saveScroll(window.location.href);

      const action = form.action || window.location.href;
      const url = new URL(action, window.location.href);

      const method = (form.method || "GET").toUpperCase();

      // multipart + files supported by FormData automatically (POST)
      const data = new FormData(form);

      this.events.onNavigateStart.forEach(fn => fn(url.href));

      const progress = new DafProgress();
      progress.show();

      let fetchUrl = url.href;
      const init = { method };

      if (method === "GET") {
        // GET cannot send body in many servers; convert to query params
        const params = new URLSearchParams(url.search);
        for (const [k, v] of data.entries()) {
          // Files in GET are ignored
          if (typeof v === "string") params.set(k, v);
        }
        const u2 = new URL(fetchUrl, window.location.href);
        u2.search = params.toString();
        fetchUrl = u2.href;
      } else {
        // POST/PUT/PATCH... body is FormData (includes files & multipart when needed)
        init.body = data;
      }

      // phase 2 on first submit as well
      this.#ensureIframeAndReplaceBody();

      return fetch(fetchUrl, init)
        .then(r => r.text().then(t => ({ text: t, finalUrl: r.url })))
        .then(({ text, finalUrl }) => {
          // update browser URL to final url (redirect aware)
          history.pushState({}, "", finalUrl || fetchUrl);
          this.#setIframeSrcdoc(text, finalUrl || fetchUrl);
        })
        .catch(err => console.error("Error loading the page:", err))
        .finally(() => {
          progress.hide();
          this.events.onNavigateEnd.forEach(fn => fn(url.href));
        });
    }
  }

  window.Daf = new Daf({
    iframeId: "daf-app-frame"
  });
</acript> -->
