(function(){
    function send(){
        var payload = JSON.stringify({
            domain: location.hostname,
            path: location.pathname
        });
        if(navigator.sendBeacon){
            navigator.sendBeacon('/api/track', payload);
        }else{
            fetch('/api/track', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: payload,
                keepalive: true
            });
        }
    }
    if(document.readyState==='complete'){
        send();
    }else{
        window.addEventListener('load', send);
    }
})();
