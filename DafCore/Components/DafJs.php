<?php
/**
 * @daf-summary Provides core Daf SPA navigation runtime, script loading, DOM morphing, and form submission hooks.
 */
?>

<script>
    (function(){
    if(window.Daf) return;

    // URL of the script to be loaded
    var scriptURL = "https://unpkg.com/morphdom@2.3.3/dist/morphdom-umd.min.js";

    // Load morphdom
    loadScript(scriptURL);

    // Function to load a script dynamically
    function loadScript(url) {
        var script = document.createElement('script'); // Create a script element
        script.type = 'text/javascript'; // Set the type of the element to text/javascript
        script.src = url; // Set the source of the script to the provided URL

        document.head.appendChild(script); // Append the script element to the head of the document
    }

    class DafEventObj {
        constructor(){
            this.isPreventDefault = false;
            this.isStopPropagation = false;
        }

        preventDefault(){
            this.isPreventDefault = true;
        }
        stopPropagation(){
            this.isStopPropagation = true;
        }
    }


    class DafProgress {
        #loaderElement = `<div class="corner-progress" aria-label="Loading" role="progressbar"></div>`

        constructor(){
            this.loader = null
            this.value = 0
        }

        #createProgressBar(){
            const oldLoader = document.getElementById("daf-page-progress")
            if(oldLoader) oldLoader.remove();

            let loader = document.createElement("div")
            loader.id = "daf-page-progress"
            //loader.innerHTML = `<div class="corner-progress" aria-label="Loading" role="progressbar"></div>`
            loader.innerHTML = this.#loaderElement
            return loader
        }
        setElement(str){
            this.#loaderElement = str
        }
        show = ()=>{
            this.loader = this.#createProgressBar();
            document.body.appendChild(this.loader)
        }

        hide(){
            this.loader.remove()
        }
    }

    class Daf {
        constructor(){
            this.events = {
                onNavigateStart:[],
                onNavigateEnd:[]
            }
            this.progress = new DafProgress()
            
            // IMPORTANT: stop the browser from auto-restoring scroll on SPA navigation
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }
            
            // Add the custom object to the Event.prototype
            Event.prototype.Daf = new DafEventObj();
            // Load the page that corresponds to the new URL
            window.addEventListener('popstate', () => {
                // we are leaving the current DOM now -> save its scroll
                this.#saveScroll(this.#currentUrl);

                const dest = window.location.href;

                // Back/Forward should restore scroll by default
                this.#planScroll(dest, { preserveScroll: true });

                this.#fetchContent(dest);
            });

            this.init()
        }


        // Scroll behavior
        #currentUrl = window.location.href;
        #scrollBehavior = ''
        #scrollBehaviorDefault = 'auto'
        #preserveScrollDefault = false;     // default: reset scroll on navigation
        #scrollStore = new Map();           // url -> {x,y}
        #pending = null;                    // { action: "reset"|"restore", url: string }

        setScrollBehaviorDefault(value){
            this.#scrollBehaviorDefault = value;
        }

        setPreserveScrollDefault(value){
            this.#preserveScrollDefault = value;
        }

        #saveScroll(url) {
            try {
                this.#scrollStore.set(url, { x: window.scrollX, y: window.scrollY });
            } catch (_) {}
        }

        #planScroll(url, options = {}) {
            const preserve = options.preserveScroll ?? this.#preserveScrollDefault;
            this.#pending = { action: preserve ? "restore" : "reset", url };

            this.#scrollBehavior = options.scrollBehavior ?? this.#scrollBehaviorDefault;
        }

        // do it NOW + next frame (beats layout shifts)
        #applyPlannedScroll() {
            const pending = this.#pending;
            this.#pending = null;
            if (!pending) return;

            const doScroll = () => {
                if (pending.action === "reset") {
                    window.scrollTo({ left: 0, top: 0, behavior: this.#scrollBehavior }); // instant
                    return;
                }
                const pos = this.#scrollStore.get(pending.url);
                if (pos) window.scrollTo({ left: pos.x, top: pos.y, behavior: this.#scrollBehavior });
            };

            doScroll();
            requestAnimationFrame(doScroll);
        }
        #readScrollOptionsFromElement(el) {
            // preserve scroll
            let preserveScroll = this.#preserveScrollDefault;
            if (el.hasAttribute("data-preserve-scroll")) {
                preserveScroll = el.getAttribute("data-preserve-scroll") === "true";
            }

            // scroll behavior
            let behavior = this.#scrollBehaviorDefault;
            if (el.hasAttribute("data-scroll-behavior")) {
                const b = el.getAttribute("data-scroll-behavior");
                behavior = b
                // if (b === "smooth") behavior = "smooth";
                // else behavior = "auto"; // "instant" or invalid → auto
            }

            return { preserveScroll, scrollBehavior: behavior };
        }



        init(){
            // init a Tags for fetch request
            this.#initATags();
            // init form Tags for fetch request
            this.#initFromTags();
        }
        addEventListener(type, callback){
            if(type === 'navigateStart')
                this.events.onNavigateStart.push(callback)
            else if(type === 'navigateEnd')
                this.events.onNavigateEnd.push(callback)
            else throw new Error('Invalid event type')
        }
        removeEventListener(type, callback){
            if(type === 'navigateStart')
            {
                let temp;
                for (let i = 0; i < this.events.onNavigateStart.length; i++) {
                    temp = this.events.onNavigateStart[i];
                    if(temp.toString() === callback.toString()){
                        this.events.onNavigateStart.splice(i, 1);
                        break;
                    }
                }
            }
            else if(type === 'navigateEnd'){
                let temp;
                for (let i = 0; i < this.events.onNavigateEnd.length; i++) {
                    temp = this.events.onNavigateEnd[i];
                    if(temp.toString() === callback.toString()){
                        this.events.onNavigateEnd.splice(i, 1);
                        break;
                    }
                }
            }
            else throw new Error('Invalid event type')
        }

        navigate(url, options = {}) {
            const urlLink = new URL(url);
            if (urlLink.href === window.location.href) return;

            // save scroll for the page we're leaving
            this.#saveScroll(this.#currentUrl);

            // plan scroll for destination
            this.#planScroll(urlLink.href, options);

            // if we're NOT preserving scroll -> scroll instantly now (before fetch)
            if (this.#pending?.action === "reset") {
                window.scrollTo({ left: 0, top: 0, behavior: this.#scrollBehavior });
            }

            this.events.onNavigateStart.forEach(e => e(url));

            history.pushState({}, '', urlLink.href);
            this.#fetchContent(urlLink.href);

            this.events.onNavigateEnd.forEach(e => e(url));
        }

        // Function to fetch the content of a page
        #fetchContent(url) {
            if (new URL(url).origin === window.location.origin) {
                this.progress.show()
                fetch(url, { method: 'GET' })
                    .then(response => response.text())
                    .then(this.#updatePage)
                    .catch(error => console.error('Error loading the page: ', error))
                    .finally(()=> this.progress.hide());
            } else {
                // If it's not the same domain, let the default action proceed
                window.location.href = url;
            }
        }
        renderHtml = (html)=>{
            this.#updatePage(html)
        }
        #updatePage = (html)=>{
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            //document.querySelector('div[data-daf-scripts]')?.remove();
            const runableScripts = doc.querySelectorAll("div[data-daf-scripts] script");
            let scripts = []
            runableScripts.forEach(s=>{
                scripts.push(s.textContent)
            })
            doc.querySelector('div[data-daf-scripts]')?.remove();

            // Use morphdom to update the current DOM element with the new one
            morphdom(document.querySelector("html"), doc.querySelector('html'));
            this.init();

            // update current url AFTER DOM swapped (now we are "on" this page)
            this.#currentUrl = window.location.href;

            // apply planned scroll
            this.#applyPlannedScroll();

            scripts.forEach(s=>{
                let script = document.createElement('script');
                script.type = 'text/javascript'; // Set the type of the element to text/javascript
                script.textContent = s;
                document.body.appendChild(script)
            })
        }

        // Function to initialize the a tags for fetch requests
        #initATags() {
            document.querySelectorAll("a:not([data-daf-ignore])").forEach(a=>{
                a.removeEventListener('click', this.#aTagClick)
                a.addEventListener('click', this.#aTagClick)
            })
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

            // 👇 NEW: read attributes
            const options = this.#readScrollOptionsFromElement(a);

            this.navigate(url, options);
        }



        // Function to initialize the from tags for fetch requests
        #initFromTags() {
            document.querySelectorAll("form:not([data-daf-ignore])").forEach(f=>{
                f.removeEventListener('submit', this.#onFromSubmit)
                f.addEventListener('submit', this.#onFromSubmit)
            })
        }
        #onFromSubmit = (e) => {
            const form = e.target;
            const url = form.action;

            if (new URL(url).origin === window.location.origin){
                e.preventDefault();
                this.#submitFormFetch(form);
            }
        }


        #collectFormData(form) {
            return new FormData(form);
        }

        #submitFormFetch(form) {
            const action = form.action;
            const method = (form.method || "GET").toUpperCase();
            const data = this.#collectFormData(form);

            this.events.onNavigateStart.forEach(e => e(action));

            // ✅ GET: encode as query string, no body
            if (method === "GET") {
                const urlObj = new URL(action, window.location.origin);

                // keep existing query params + add form fields
                const params = new URLSearchParams(urlObj.search);
                for (const [k, v] of data.entries()) {
                    // skip empty fields if you want (optional)
                    if (v === "" || v === null) continue;
                    params.set(k, String(v));
                }
                urlObj.search = params.toString();

                // SPA navigate (preserves your scroll logic, history, etc.)
                this.navigate(urlObj.href);
                this.events.onNavigateEnd.forEach(e => e(urlObj.href));
                return;
            }

            // ✅ non-GET: send body normally
            return fetch(action, { method, body: data })
                .then(response => {
                    history.pushState({}, '', response.url);
                    return response.text();
                })
                .then(this.#updatePage)
                .catch(error => console.error('Error loading the page: ', error))
                .finally(() => {
                    this.events.onNavigateEnd.forEach(e => e(action));
                });
        }

        
        submitForm(formOrId, options = {}) {
            const form = typeof formOrId === "string"
                ? document.getElementById(formOrId)
                : formOrId;

            if (!form) return;

            const url = form.action;
            if (new URL(url).origin !== window.location.origin) {
                form.submit(); // fallback native
                return;
            }

            this.#saveScroll(this.#currentUrl);
            this.#planScroll(url, options);

            if (this.#pending?.action === "reset") {
                window.scrollTo({ left: 0, top: 0, behavior: this.#scrollBehavior });
            }

            return this.#submitFormFetch(form);
        }

    }

    window.Daf = new Daf()
})();
</script>