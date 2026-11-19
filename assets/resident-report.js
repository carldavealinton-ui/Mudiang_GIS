(() => {
    const form=document.getElementById('complaintForm');if(!form)return;
    const fields=['complaint_type','description','address','latitude','longitude','priority'];
    let nearbyTimer,nearbyVersion=0;
    function values(){const data=new FormData(form);return Object.fromEntries(fields.map(field=>[field,String(data.get(field)||'')]));}

    const key=`mudiang-report-draft-${form.dataset.resident}`;
    const status=document.getElementById('draft-status');
    const restore=document.getElementById('restore-draft');
    let pendingDraft=null,timer,saveChain=Promise.resolve(),stopped=false;
    const storage={get(){try{return JSON.parse(localStorage.getItem(key));}catch(_){return null;}},set(value){try{localStorage.setItem(key,JSON.stringify(value));return true;}catch(_){return false;}},clear(){try{localStorage.removeItem(key);}catch(_){}}};
    async function postDraft(payload,action='save'){
        const body=new URLSearchParams({...payload,action,csrf_token:form.dataset.draftToken});
        const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),10000);
        try {
            const response=await fetch('resident_draft.php',{method:'POST',body,signal:controller.signal});
            const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Unable to save draft.');return data;
        }finally{clearTimeout(timeout);}
    }
    function save(){
        if(stopped)return saveChain;
        const payload=values();const localSaved=storage.set({payload,savedAt:Date.now()});
        status.textContent=localSaved?'Saved on this device. Syncing…':'Saving draft…';
        saveChain=saveChain.catch(()=>{}).then(()=>postDraft(payload)).then(()=>{status.textContent='Draft saved to your account.';pendingDraft=payload;restore.hidden=false;}).catch(()=>{status.textContent=localSaved?'Saved on this device. Account sync will retry when you reconnect.':'Draft could not be saved. Check your connection and try again.';});
        return saveChain;
    }
    function schedule(){clearTimeout(timer);if(!stopped)timer=setTimeout(save,1200);}
    form.addEventListener('input',event=>{if(fields.includes(event.target.name))schedule();});
    form.addEventListener('change',event=>{if(fields.includes(event.target.name))schedule();});
    document.addEventListener('report-location-changed',schedule);
    document.getElementById('save-draft').addEventListener('click',()=>{clearTimeout(timer);save();});
    restore.addEventListener('click',()=>{
        if(!pendingDraft)return;
        fields.forEach(field=>{
            if(field==='priority'){const input=form.querySelector(`input[name="priority"][value="${['low','medium','high'].includes(pendingDraft.priority)?pendingDraft.priority:'medium'}"]`);if(input)input.checked=true;}
            else {const input=form.elements.namedItem(field);if(input)input.value=String(pendingDraft[field]||'');}
        });
        const lat=Number(pendingDraft.latitude),lng=Number(pendingDraft.longitude);
        if(pendingDraft.latitude&&pendingDraft.longitude&&Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180){placeLocationPin(lat,lng);map.setView([lat,lng],17);}
        status.textContent='Draft restored. Select your evidence files again before submitting.';
        checkNearby();
    });
    document.getElementById('discard-draft').addEventListener('click',async()=>{
        clearTimeout(timer);await saveChain;storage.clear();pendingDraft=null;restore.hidden=true;
        try{await postDraft({},'delete');status.textContent='Saved draft discarded. Your current form is still available.';}catch(_){status.textContent='Device draft removed. Account draft could not be removed; try again.';}
    });
    const local=storage.get();
    if(local?.payload){pendingDraft=local.payload;restore.hidden=false;status.textContent='A draft is available on this device. Restore it to continue.';}
    fetch('resident_draft.php').then(response=>response.json()).then(data=>{
        if(!data.draft)return;
        const serverTime=Date.parse(String(data.updated_at).replace(' ','T')+'+08:00');
        if(!local?.payload||!Number.isFinite(serverTime)||local.savedAt<=serverTime)pendingDraft=data.draft;
        restore.hidden=false;
        if(!status.textContent)status.textContent='A saved draft is available. Restore it to continue.';
    }).catch(()=>{});
    window.addEventListener('online',()=>{if(!stopped)save();});
    form.prepareDraftSubmission=()=>{stopped=true;clearTimeout(timer);return saveChain;};
    form.addEventListener('report-submit-failed',()=>{stopped=false;});
    form.addEventListener('report-submitted',()=>{stopped=true;clearTimeout(timer);storage.clear();});

    const nearby=document.getElementById('nearby-issues');
    async function checkNearby(){
        const version=++nearbyVersion;
        const data=values();
        if(!data.latitude||!data.longitude||!data.complaint_type){nearby.replaceChildren();return;}
        nearby.textContent='Checking for similar nearby issues…';
        try{
            const params=new URLSearchParams({latitude:data.latitude,longitude:data.longitude,category:data.complaint_type});
            const response=await fetch(`nearby_issues.php?${params}`);const result=await response.json();
            if(version!==nearbyVersion)return;
            if(!response.ok)throw new Error(result.error);
            nearby.replaceChildren();if(!result.items.length)return;
            const heading=document.createElement('strong');heading.textContent='Similar issues nearby';
            const hint=document.createElement('p');hint.textContent='If this is the same concern, choose “I’m also affected” and track it on your dashboard. You can still submit a separate report.';
            nearby.append(heading,hint);
            result.items.forEach(item=>{
                const row=document.createElement('div');row.className='nearby-issue';
                const text=document.createElement('span');text.textContent=`#${item.id} · ${item.complaint_type} · ${item.status} · About ${item.distance} m away · ${item.supporters} supporting`;
                const button=document.createElement('button');button.type='button';button.className='btn btn-outline';button.textContent=item.supported?'You support this issue':'I’m also affected';button.disabled=item.supported;
                button.addEventListener('click',async()=>{
                    button.disabled=true;
                    try{
                        const response=await fetch('support_issue.php',{method:'POST',body:new URLSearchParams({complaint_id:item.id,csrf_token:form.dataset.draftToken,latitude:data.latitude,longitude:data.longitude,category:data.complaint_type})});
                        const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error);
                        button.textContent='You support this issue';
                    }catch(error){button.disabled=false;window.alert(error.message||'Unable to support this issue.');}
                });
                row.append(text,button);nearby.append(row);
            });
        }catch(_){if(version===nearbyVersion)nearby.textContent='Nearby issues are unavailable. You can still submit your report.';}
    }
    function scheduleNearby(){nearbyVersion++;clearTimeout(nearbyTimer);nearbyTimer=setTimeout(checkNearby,350);}
    document.addEventListener('report-location-changed',scheduleNearby);
    document.getElementById('complaint-type').addEventListener('change',scheduleNearby);
    checkNearby();
})();
