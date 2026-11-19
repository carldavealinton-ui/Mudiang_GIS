const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs');const os=require('node:os');const path=require('node:path');const assert=require('node:assert/strict');
const php='C:/xampp/php/php.exe';const fixture=JSON.parse(execFileSync(php,['tests/resident_browser_fixture.php'],{encoding:'utf8'}));
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'mudiang-browser-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--remote-debugging-port=9227',`--user-data-dir=${profile}`,'--no-first-run','--no-default-browser-check','--disable-gpu','about:blank'],{windowsHide:true,stdio:'ignore'});
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
async function run(){
 let pages;
 for(let i=0;i<30;i++){try{pages=await(await fetch('http://127.0.0.1:9227/json')).json();break;}catch(_){await sleep(200);}}
 if(!pages)throw new Error('Browser debugging endpoint unavailable.');
 socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
 let seq=0;const pending=new Map();const errors=[];
 socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(msg.id){const task=pending.get(msg.id);pending.delete(msg.id);if(msg.error)task.reject(new Error(msg.error.message));else task.resolve(msg.result);}if(msg.method==='Runtime.exceptionThrown')errors.push(msg.params.exceptionDetails.text+': '+(msg.params.exceptionDetails.exception?.description||''));});
 function command(method,params={}){return new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});}
 async function evaluate(expression){const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||'Browser evaluation failed.');return result.result.value;}
 async function until(expression){for(let i=0;i<50;i++){if(await evaluate(expression))return;await sleep(150);}throw new Error('Browser condition timed out: '+expression);}
 async function visit(page){await command('Page.navigate',{url:'http://localhost/System/'+page});await until("document.readyState==='complete' && Boolean(document.getElementById('portal-language'))");}
 await command('Page.enable');await command('Runtime.enable');await command('Network.enable');
 const login=await fetch('http://localhost/System/index.php',{method:'POST',body:new URLSearchParams({username:fixture.username,password:fixture.password}),redirect:'manual'});
 assert.equal(login.status,302);const session=login.headers.getSetCookie().filter(cookie=>cookie.startsWith('PHPSESSID=')).at(-1).split(';')[0].slice(10);
 await command('Network.setCookie',{name:'PHPSESSID',value:session,url:'http://localhost/System/'});
 await visit('resident_dashboard.php');assert(await evaluate("Boolean(document.getElementById('report-search'))"));console.log('PASS: Dashboard renders filters and language control');
 await evaluate("document.getElementById('portal-language').value='ceb';document.getElementById('portal-language').dispatchEvent(new Event('change'))");
 assert.equal(await evaluate("document.documentElement.lang"),'ceb');
 assert(await evaluate("document.body.textContent.includes('Akong profile')"));
 assert.equal(await evaluate("document.querySelector('#filter-category option:nth-child(2)').value"),'Road Damage');console.log('PASS: Cebuano interface preserves category submission values');
 await visit('submit_complaint_form.php');await until("typeof map!=='undefined' && typeof placeLocationPin==='function'");
 await evaluate("document.getElementById('portal-language').value='en';document.getElementById('portal-language').dispatchEvent(new Event('change'))");
 await evaluate("document.getElementById('description').value='Browser saved draft';document.getElementById('description').dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('complaint-type').value='Road Damage';document.getElementById('complaint-type').dispatchEvent(new Event('change'));placeLocationPin(7.182708,125.622193);document.getElementById('save-draft').click()");
 await until("document.getElementById('draft-status').textContent==='Draft saved to your account.'");console.log('PASS: Browser saves account draft');
 await command('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
 await evaluate("document.getElementById('description').value='Offline browser draft';document.getElementById('save-draft').click()");
 await until("document.getElementById('draft-status').textContent.includes('Saved on this device') && !document.getElementById('draft-status').textContent.includes('Syncing')");
 await command('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
 await evaluate("window.dispatchEvent(new Event('online'))");await until("document.getElementById('draft-status').textContent==='Draft saved to your account.'");console.log('PASS: Offline draft syncs after reconnecting');
 await until("document.querySelector('#nearby-issues button')!==null");await evaluate("document.querySelector('#nearby-issues button').click()");await until("document.querySelector('#nearby-issues button').textContent==='You support this issue'");console.log('PASS: Nearby issue support works in browser');
 await visit('submit_complaint_form.php?draft=1');assert.equal(await evaluate("document.getElementById('description').value"),'Offline browser draft');console.log('PASS: Draft continues after navigation');
 await command('Emulation.setGeolocationOverride',{latitude:7.183,longitude:125.623,accuracy:20});
 await command('Browser.grantPermissions',{origin:'http://localhost',permissions:['geolocation']});
 await evaluate("document.getElementById('current-location').click()");await until("document.getElementById('location-status').textContent.includes('20 metres')");assert.equal(await evaluate("document.getElementById('latitude').value"),'7.1830000');console.log('PASS: Current location updates coordinates');
 await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
 assert(await evaluate("document.documentElement.scrollWidth<=window.innerWidth+1"));console.log('PASS: Report form fits mobile viewport');
 await evaluate(`(async()=>{
   const canvas=document.createElement('canvas');canvas.width=2400;canvas.height=1800;
   const context=canvas.getContext('2d');const image=context.createImageData(canvas.width,canvas.height);
   for(let i=0;i<image.data.length;i+=4){image.data[i]=Math.random()*255;image.data[i+1]=Math.random()*255;image.data[i+2]=Math.random()*255;image.data[i+3]=255;}
   context.putImageData(image,0,0);const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',1));
   window.fixtureOriginalPhotoSize=blob.size;
   const files=new DataTransfer();files.items.add(new File([blob],'browser-fixture.jpg',{type:'image/jpeg'}));
   const input=document.getElementById('evidence-files');input.files=files.files;input.dispatchEvent(new Event('change'));
 })()`);
 await until("!document.getElementById('submit-report').disabled && document.querySelector('.selected-evidence')!==null");
 assert(await evaluate("document.getElementById('evidence-files').files[0].size < window.fixtureOriginalPhotoSize"));console.log('PASS: Large photo is reduced before upload');
 await evaluate("document.getElementById('report-address').value='Browser fixture landmark';document.getElementById('portal-language').value='fil';document.getElementById('portal-language').dispatchEvent(new Event('change'));document.getElementById('submit-report').click()");
 await until("location.pathname.endsWith('resident_dashboard.php') && location.search.includes('success=1')");
 assert.equal(await evaluate("localStorage.getItem('mudiang-report-draft-'+"+fixture.users[0]+")"),null);
 assert(await evaluate("fetch('resident_draft.php').then(r=>r.json()).then(r=>r.draft===null)"));console.log('PASS: Translated form submits evidence and clears device/account drafts');
 await visit('profile.php');assert(await evaluate("Boolean(document.getElementById('full_name'))"));
 await evaluate("document.getElementById('portal-language').value='fil';document.getElementById('portal-language').dispatchEvent(new Event('change'))");assert(await evaluate("document.body.textContent.includes('I-save ang profile')"));console.log('PASS: Profile and Filipino interface render');
 assert.equal(errors.length,0,errors.join('\n'));console.log('Browser checks passed without JavaScript exceptions.');
 await command('Browser.close');
}
run().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
 if(socket)socket.close();browser.kill();
 execFileSync(php,['tests/resident_browser_fixture.php','cleanup',...fixture.users.map(String)],{stdio:'ignore'});
 await sleep(1000);
 const resolved=path.resolve(profile),tmpRoot=path.resolve(os.tmpdir())+path.sep;
 if(!resolved.startsWith(tmpRoot)||!path.basename(resolved).startsWith('mudiang-browser-'))throw new Error('Browser cleanup path rejected.');
 try{fs.rmSync(resolved,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch(_){console.log('Temporary browser profile remains at '+resolved);}
});
