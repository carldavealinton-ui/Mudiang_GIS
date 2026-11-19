(() => {
    // Translate interface copy only; resident messages and staff announcements retain their original text.
    const phrases={
      'Language':['Wika','Pinulongan'],
      'My Dashboard':['Aking dashboard','Akong dashboard'],
      'Dashboard':['Dashboard','Dashboard'],
      'Messages':['Mga mensahe','Mga mensahe'],
      'Submit Complaint':['Magsumite ng reklamo','Pagsumite og reklamo'],
      'Public Map':['Mapa ng komunidad','Mapa sa komunidad'],
      'My profile':['Aking profile','Akong profile'],
      'Account security':['Seguridad ng account','Seguridad sa account'],
      'Sign Out':['Mag-sign out','Pag-sign out'],
      'Resident':['Residente','Residente'],
      'Menu':['Menu','Menu'],
      'My Complaints':['Aking mga reklamo','Akong mga reklamo'],
      'Total':['Kabuuan','Kinatibuk-an'],
      'Filed':['Naisumite','Nasumite'],
      'Acknowledged':['Natanggap','Nadawat'],
      'Processing':['Inaaksyunan','Gilihok'],
      'Resolved':['Nalutas','Nasulbad'],
      'filed':['naisumite','nasumite'],
      'acknowledged':['natanggap','nadawat'],
      'processing':['inaaksyunan','gilihok'],
      'resolved':['nalutas','nasulbad'],
      'Track report':['Subaybayan ang reklamo','Subaybayi ang reklamo'],
      'Review resolution':['Suriin ang solusyon','Susihon ang solusyon'],
      'Message staff':['Magmensahe sa kawani','Mensahi ang kawani'],
      'Search reports':['Maghanap ng reklamo','Pangitaa ang reklamo'],
      'Search description or address':['Hanapin ang paglalarawan o address','Pangitaa ang paghulagway o adres'],
      'Status':['Katayuan','Kahimtang'],
      'Category':['Kategorya','Kategorya'],
      'All statuses':['Lahat ng katayuan','Tanang kahimtang'],
      'All categories':['Lahat ng kategorya','Tanang kategorya'],
      'From date':['Mula sa petsa','Gikan sa petsa'],
      'To date':['Hanggang sa petsa','Hangtod sa petsa'],
      'Apply filters':['Ilapat ang mga filter','Gamita ang mga filter'],
      'Clear filters':['Alisin ang mga filter','Tangtanga ang mga filter'],
      'Previous':['Nakaraan','Miagi'],
      'Next':['Susunod','Sunod'],
      'No reports found':['Walang nahanap na reklamo','Walay nakit-an nga reklamo'],
      'Try different filters or submit a new report.':['Subukan ang ibang filter o magsumite ng bagong reklamo.','Sulayi ang laing filter o pagsumite og bag-ong reklamo.'],
      'Barangay announcements':['Mga anunsyo ng barangay','Mga pahibalo sa barangay'],
      'No active announcements. Check back for community advisories.':['Walang aktibong anunsyo. Balikan para sa mga abiso ng komunidad.','Walay aktibong pahibalo. Balik alang sa mga abiso sa komunidad.'],
      'Issues I support':['Mga isyung sinusuportahan ko','Mga isyu nga akong gisuportahan'],
      'Continue your report':['Ipagpatuloy ang iyong reklamo','Ipadayon ang imong reklamo'],
      'You have a saved draft.':['Mayroon kang naka-save na draft.','Aduna kay na-save nga draft.'],
      'Next step:':['Susunod na hakbang:','Sunod nga lakang:'],
      'Staff will review and acknowledge your report. You can add details through Messages.':['Susuriin at tatanggapin ng kawani ang iyong reklamo. Maaari kang magdagdag ng detalye sa Mga mensahe.','Susihon ug dawaton sa kawani ang imong reklamo. Mahimo kang modugang og detalye pinaagi sa Mga mensahe.'],
      'Your report has been received. Staff will arrange the next action and target date.':['Natanggap na ang iyong reklamo. Itatakda ng kawani ang susunod na aksyon at target na petsa.','Nadawat na ang imong reklamo. Itakda sa kawani ang sunod nga aksyon ug target nga petsa.'],
      'Staff are working on your concern. Check the timeline for updates or message the team.':['Inaaksyunan na ang iyong reklamo. Tingnan ang mga update o magmensahe sa kawani.','Gilihok na sa kawani ang imong reklamo. Tan-awa ang mga update o mensahi ang grupo.'],
      'Review the resolution. Confirm it is fixed or request reopening if the concern remains.':['Suriin ang solusyon. Kumpirmahing naayos o hilinging buksan muli kung may problema pa.','Susihon ang solusyon. Kumpirmaha kung naayo o hangyoa nga ablihan pag-usab kung naa pa ang problema.'],
      'Make your report':['Gawin ang iyong reklamo','Himoa ang imong reklamo'],
      'Required fields are marked *':['May * ang mga kailangang sagutan','Ang gikinahanglang mga field adunay *'],
      'Describe the concern':['Ilarawan ang problema','Ihulagway ang problema'],
      'A clear description helps the team understand your report.':['Makakatulong ang malinaw na paglalarawan para maunawaan ang iyong reklamo.','Ang klarong paghulagway makatabang sa pagsabot sa imong reklamo.'],
      'Choose a category':['Pumili ng kategorya','Pilia ang kategorya'],
      'What happened?':['Ano ang nangyari?','Unsay nahitabo?'],
      'Choose the importance level':['Piliin ang antas ng kahalagahan','Pilia ang lebel sa kahinungdanon'],
      'Low':['Mababa','Ubos'],
      'Medium':['Katamtaman','Kasagaran'],
      'High':['Mataas','Taas'],
      'Minor inconvenience':['Maliit na abala','Gamay nga kasamok'],
      'Needs attention':['Nangangailangan ng aksyon','Nagkinahanglan og pagtagad'],
      'Significant impact':['Malaking epekto','Dakong epekto'],
      'Pinpoint the location':['Ituro ang lokasyon','Itudlo ang lokasyon'],
      'Place a pin where the concern is happening.':['Maglagay ng pin sa lugar ng problema.','Ibutang ang pin sa dapit sa problema.'],
      'Current location':['Kasalukuyang lokasyon','Kasamtangang lokasyon'],
      'Use your device location, then adjust the pin if needed.':['Gamitin ang lokasyon ng iyong device, at ayusin ang pin kung kailangan.','Gamita ang lokasyon sa imong device, ug i-adjust ang pin kon gikinahanglan.'],
      'Allow location access when your browser asks.':['Payagan ang lokasyon kapag humingi ang browser.','Tugoti ang lokasyon kon mangayo ang browser.'],
      'Click or tap the map to place your pin':['I-click o i-tap ang mapa para maglagay ng pin','I-click o i-tap ang mapa aron ibutang ang pin'],
      'Address or landmark':['Address o palatandaan','Adres o timaan'],
      'Share supporting evidence':['Magdagdag ng ebidensya','Pagdugang og ebidensya'],
      'Optional':['Opsyonal','Opsyonal'],
      'Choose photos or videos':['Pumili ng larawan o video','Pilia ang hulagway o video'],
      'Your evidence is visible to you and authorized barangay staff.':['Ikaw at awtorisadong kawani lamang ang makakakita ng iyong ebidensya.','Ikaw ug awtorisadong kawani lamang ang makakita sa imong ebidensya.'],
      'Cancel':['Kanselahin','Kanselahon'],
      'Submit report':['Isumite ang reklamo','Isumite ang reklamo'],
      'Save your progress':['I-save ang iyong nagawa','I-save ang imong nahimo'],
      'Text and map pins are saved automatically. Attachments must be selected again when you return.':['Awtomatikong sine-save ang teksto at pin. Piliin muli ang mga attachment kapag bumalik ka.','Awtomatikong ma-save ang teksto ug pin. Pilia pag-usab ang mga attachment inig balik nimo.'],
      'Save draft':['I-save ang draft','I-save ang draft'],
      'Restore saved draft':['Ibalik ang naka-save na draft','Ibalik ang na-save nga draft'],
      'Discard draft':['Alisin ang draft','Tangtanga ang draft'],
      'Draft saved to your account.':['Na-save ang draft sa iyong account.','Na-save ang draft sa imong account.'],
      'Similar issues nearby':['Katulad na isyu sa malapit','Susamang isyu sa duol'],
      'I’m also affected':['Apektado rin ako','Apektado usab ko'],
      'You support this issue':['Sinusuportahan mo ang isyung ito','Gisuportahan nimo kini nga isyu'],
      'Road Damage':['Sirang kalsada','Guba nga dalan'],
      'Drainage Issue':['Problema sa kanal','Problema sa kanal'],
      'Street Light':['Ilaw sa kalsada','Suga sa dalan'],
      'Garbage Collection':['Koleksyon ng basura','Pagkolekta sa basura'],
      'Noise Complaint':['Reklamo sa ingay','Reklamo sa kasaba'],
      'Water Supply':['Suplay ng tubig','Suplay sa tubig'],
      'Other':['Iba pa','Uban pa'],
      'Full name':['Buong pangalan','Tibuok ngalan'],
      'Contact number':['Numero ng telepono','Numero sa telepono'],
      'Home address':['Address ng tahanan','Adres sa balay'],
      'Save profile':['I-save ang profile','I-save ang profile'],
      'Profile updated.':['Na-update ang profile.','Na-update ang profile.'],
      'Back to dashboard':['Bumalik sa dashboard','Balik sa dashboard'],
      'Forgot password':['Nakalimutan ang password','Nalimtan ang password'],
      'Email address':['Email address','Email address'],
      'Send reset link':['Ipadala ang reset link','Ipadala ang reset link'],
      'Reset password':['Palitan ang password','Ilisi ang password'],
      'New password':['Bagong password','Bag-ong password'],
      'Confirm password':['Kumpirmahin ang password','Kumpirmaha ang password'],
      'Back to sign in':['Bumalik sa pag-sign in','Balik sa pag-sign in'],
      'Your complaint timeline':['Mga update sa iyong reklamo','Mga update sa imong reklamo'],
      'Confirm resolution':['Kumpirmahin ang solusyon','Kumpirmaha ang solusyon'],
      'Request reopening':['Hilinging buksan muli','Hangyoa nga ablihan pag-usab'],
      'Awaiting scheduling':['Naghihintay ng iskedyul','Naghulat sa iskedyul']
    };
    let language='en';try{language=localStorage.getItem('mudiang-language')||'en';}catch(_){}
    if(!['en','fil','ceb'].includes(language))language='en';
    const header=document.querySelector('.topbar');
    let container;
    if(header){container=header.querySelector('.topbar-right');if(!container){container=document.createElement('div');container.className='topbar-right';header.append(container);}}
    else container=document.querySelector('.reg-box,.login-box,.login-container,.content');
    if(!container)return;
    const control=document.createElement('div');control.className='portal-language';
    const label=document.createElement('label');label.htmlFor='portal-language';label.textContent='Language';
    const select=document.createElement('select');select.id='portal-language';
    [['en','English'],['fil','Filipino'],['ceb','Cebuano']].forEach(([value,text])=>{const option=document.createElement('option');option.value=value;option.textContent=text;select.append(option);});
    select.value=language;control.append(label,select);if(header)container.append(control);else container.prepend(control);
    const originals=new WeakMap();const attributeOriginals=new WeakMap();
    document.querySelectorAll('option:not([value])').forEach(option=>{option.value=option.textContent;});
    const skip='script,style,textarea,.user-name,.ci-desc,.ci-meta,.resolution-box,.community-advisory,.conversation-timeline,.inbox-preview,.inbox-resident-name,.notif-msg,.report-activity-item,.thread-context dl,#evidence-selection,#evidence-feedback,#evidence-help';
    const translated=value=>language==='en'?value:(phrases[value]?.[language==='fil'?0:1]||value);
    function apply(){
        observer.disconnect();document.documentElement.lang=language;
        const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
        let node;
        while((node=walker.nextNode())){
            if(node.parentElement.closest(skip)||node.parentElement.closest('#portal-language'))continue;
            let record=originals.get(node);
            if(!record||node.nodeValue!==record.output)record={source:node.nodeValue,output:node.nodeValue};
            const text=record.source.trim();const base=text.replace(/\s*\*$/,'');
            const replacement=translated(base)+(text.endsWith('*')?' *':'');
            record.output=record.source.replace(text,replacement);node.nodeValue=record.output;originals.set(node,record);
        }
        document.querySelectorAll('input[placeholder],textarea[placeholder]').forEach(input=>{
            if(!attributeOriginals.has(input))attributeOriginals.set(input,input.placeholder);
            input.placeholder=translated(attributeOriginals.get(input));
        });
        observer.observe(document.body,{childList:true,characterData:true,subtree:true});
    }
    let timer;const observer=new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(apply,80);});
    select.addEventListener('change',()=>{language=select.value;try{localStorage.setItem('mudiang-language',language);}catch(_){}apply();});
    apply();
})();
