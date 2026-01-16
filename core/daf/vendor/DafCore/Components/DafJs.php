<Script>

(function() {
if (window.Daf) return;

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
    constructor(){
        this.loader = null
        this.value = 0
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
        </div>
        `

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
        // Add the custom object to the Event.prototype
        Event.prototype.Daf = new DafEventObj();
        // Load the page that corresponds to the new URL
        window.addEventListener('popstate', ()=> this.#fetchContent(window.location.href));
        this.init()
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

    navigate(url){
        // Check if the URL is the same as the current one
        const urlLink = new URL(url)
        if(urlLink.href === window.location.href) return

        this.events.onNavigateStart.forEach(e => e(url))
    
        // Update the URL in the browser without reloading the page
        history.pushState({}, '', url)
        this.#fetchContent(url)

        this.events.onNavigateEnd.forEach(e => e(url))
    }

    // Function to fetch the content of a page
    #fetchContent(url) {
        if (new URL(url).origin === window.location.origin) {
            const progress = new DafProgress()
            progress.show()
            fetch(url, { method: 'GET' })
                .then(response => response.text())
                .then(this.#updatePage)
                .catch(error => console.error('Error loading the page: ', error))
                .finally(()=> progress.hide());
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
        morphdom(document.querySelector("html"), doc.querySelector('html'))
        this.init()

        scripts.forEach(s=>{
            let script = document.createElement('script');
            script.type = 'text/javascript'; // Set the type of the element to text/javascript
            script.textContent = s;
            document.body.appendChild(script)
        })
    }

    // Function to initialize the a tags for fetch requests
    #initATags() {
        document.querySelectorAll("a").forEach(a=>{
            a.removeEventListener('click', this.#aTagClick)
            a.addEventListener('click', this.#aTagClick)
        })
    }
    #aTagClick = (e)=> {
        if(e.Daf){
            if(e.Daf.isPreventDefault === true) return
            if(e.Daf.isStopPropagation === true) e.stopPropagation()
        }
        
        e.preventDefault()
        const url = e.currentTarget.href
        if(!URL.canParse(url)) return
        
        e.preventDefault()
        this.navigate(url)
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
            
            e.preventDefault()

            const method = form.method;
            const data = new FormData(form);
            
            this.events.onNavigateStart.forEach(e => e(url))

            fetch(url, { method, body: data })
                .then(response => {
                    // Update the URL in the browser without reloading the page
                    history.pushState({}, '', response.url);
                    return response.text()
                })
                .then(this.#updatePage)
                .catch(error => console.error('Error loading the page: ', error));

            this.events.onNavigateEnd.forEach(e => e(url))
        }
    }
}

window.Daf = new Daf()

})();
</Script>