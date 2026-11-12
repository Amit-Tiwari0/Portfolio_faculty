(() => {
  const data=JSON.parse(JSON.stringify(window.adminPortfolioData));
  const passwordToggle=document.getElementById('toggle-password-panel');
  const passwordPanel=document.getElementById('password-panel');
  const cancelPassword=document.getElementById('cancel-password-panel');
  const setPasswordPanel=show=>{if(!passwordPanel)return;passwordPanel.hidden=!show;passwordToggle?.setAttribute('aria-expanded',String(show));if(show){passwordPanel.scrollIntoView({behavior:'smooth',block:'center'});passwordPanel.querySelector('[name="current_password"]')?.focus()}};
  passwordToggle?.addEventListener('click',()=>setPasswordPanel(passwordPanel.hidden));
  cancelPassword?.addEventListener('click',()=>setPasswordPanel(false));
  const profileFields=document.getElementById('profile-fields');
  const profileDetailFields=document.getElementById('profile-detail-fields');
  const socialLinkFields=document.getElementById('social-link-fields');
  const labels={name:'Full name',role:'Designation',department:'Department',institution:'College / institution',affiliation_short:'Short affiliation',additional_role:'Additional appointment',location:'Location',bio:'Biography',email:'Email (bottom contact section)',phone:'Phone (bottom contact section)',linkedin:'LinkedIn URL (bottom contact section)',social_title:'Link name',social_url:'Link URL',teaching_url:'Default teaching link (used when a course has no individual link)',image_alt:'Photo description',url:'Link URL (optional; makes the title clickable)',indexing:'Indexing information',scopus:'Scopus status',impact_factor:'Impact Factor value',category:'Category',type:'Publication type',value:'Entry text',degree:'Degree / qualification',school:'Institution / school',place:'Location',student_name:'Name',roll_no:'Roll No.',university:'University',student_year:'Year (start–end)',problem_statement:'Problem statement'};
  const longFields=new Set(['bio','detail','topic','description','summary','department','institution','affiliation_short','additional_role','location','school','organization','venue','authors','problem_statement']);
  const collectionNames={notices:'Notice',research_interests:'Research interests',education:'Education',teaching:'Teaching',publications:'Publications',projects:'Projects',experience:'Experience',supervision:'Student supervision',skills:'Technical skills',awards:'Awards and recognition',service:'Academic service',talks:'Talks and outreach'};
  const settingsNames={...collectionNames,about:'About',contact:'Contact'};
  const starter={notices:{title:'',date:'',detail:'',url:'',image_alt:''},research_interests:{name:'',url:''},education:{period:'',degree:'',school:'',detail:'',url:''},teaching:{title:'',url:data.profile?.teaching_url||''},publications:{year:'',type:'Journal article',category:'International Journals',title:'',authors:'',venue:'',url:'',topic:'',indexing:'',scopus:'',impact_factor:'',image_alt:''},projects:{period:'',kind:'',title:'',organization:'',detail:'',value:'',url:''},experience:{period:'',role:'',organization:'',place:'',url:''},supervision:{category:'',student_name:'',roll_no:'',university:'',student_year:'',problem_statement:'',url:''},skills:{name:'',url:''},awards:{year:'',title:'',detail:'',url:''},service:{title:'',detail:'',url:''},talks:{title:'',venue:'',date:'',url:''}};
  for(const key of Object.keys(collectionNames))if(!Array.isArray(data[key]))data[key]=[];
  if(!Array.isArray(data.custom_sections))data.custom_sections=[];
  for(const key of ['research_interests','skills'])data[key]=data[key].map(item=>typeof item==='string'?{name:item,url:''}:item);
  for(const key of Object.keys(collectionNames))if(!['research_interests','skills'].includes(key))data[key]=data[key].map(item=>typeof item==='string'?{title:item,url:''}:{url:'',...item});
  const classifySupervision=value=>{const text=String(value||'').toLowerCase();if(/ph\.?d|doctoral/.test(text))return'PhD Students';if(/m\.?\s*tech|master/.test(text))return'M.Tech Students';if(/b\.?\s*tech|bachelor/.test(text))return'B.Tech Students';if(/intern/.test(text))return'Interns';return String(value||'').trim()||'PhD Students'};
  data.supervision=data.supervision.map(item=>{const oldDegree=item.degree||'';const yearMatch=String(oldDegree).match(/(?:19|20)\d{2}\s*[–—-]\s*(?:Present|(?:19|20)\d{2})/i);return{category:item.category?String(item.category).trim():classifySupervision(oldDegree),student_name:item.student_name||item.name||item.student||item.title||'',roll_no:item.roll_no||item.roll_number||'',university:item.university||item.institution||item.organization||'',student_year:item.student_year||item.year||(yearMatch?yearMatch[0]:''),problem_statement:item.problem_statement||item.topic||item.detail||'',url:item.url||''}});
  const defaultSupervisionCategories=['PhD Students','M.Tech Students','B.Tech Students','Interns'];
  const uniqueNames=values=>[...new Set(values.map(value=>String(value||'').trim()).filter(Boolean))];
  data.supervision_categories=uniqueNames([...(Array.isArray(data.supervision_categories)?data.supervision_categories:defaultSupervisionCategories),...data.supervision.map(item=>item.category)]);
  if(!data.supervision_categories.length)data.supervision_categories=defaultSupervisionCategories;
  data.custom_sections=data.custom_sections.map((item,i)=>({id:item.id||`custom_${Date.now().toString(36)}_${i}`,visible:item.visible!==false,...item}));
  data.social_links=Array.isArray(data.social_links)?data.social_links.filter(item=>item&&(String(item.title||'').trim()||String(item.url||'').trim())).map(item=>({title:item.title||'',url:item.url||''})):[];
  const makeField=(key,value)=>{const wrap=document.createElement('label');wrap.className='field';const cap=document.createElement('span');cap.textContent=labels[key]||key.replace(/[_-]/g,' ').replace(/\b\w/g,c=>c.toUpperCase());wrap.append(cap);const control=longFields.has(key)?document.createElement('textarea'):document.createElement('input');if(control.tagName==='INPUT')control.type=key==='email'?'email':'text';else control.rows=key==='bio'?5:2;control.value=value==null?'':String(value);control.dataset.key=key;control.autocomplete='off';wrap.append(control);return wrap};
  for(const [key,value] of Object.entries(data.profile||{})){if(['photo','scholar','orcid','role','department','institution','affiliation_short','additional_role','location'].includes(key))continue;profileFields.append(makeField(key,value))}
  const legacyDetails=[['designation','Designation','role'],['department','Department','department'],['institution','Institution','institution'],['additional_appointment','Additional appointment','additional_role'],['location','Location','location']];
  if(!Array.isArray(data.profile_details))data.profile_details=legacyDetails.filter(([, ,key])=>data.profile?.[key]).map(([id,label,key])=>({id,label,value:data.profile[key],url:'',visible:true}));
  data.profile_details=data.profile_details.map((item,index)=>({id:item.id||`detail_${Date.now().toString(36)}_${index}`,label:item.label||'',value:item.value||'',url:item.url||'',visible:item.visible!==false}));
  const makeDetailInput=(label,value,key,type='text')=>{const wrap=document.createElement('label');wrap.className='field';const caption=document.createElement('span');caption.textContent=label;const input=document.createElement('input');input.type=type;input.value=value||'';input.dataset.profileDetail=key;input.autocomplete='off';wrap.append(caption,input);return wrap};
  const renderProfileDetails=()=>{
    profileDetailFields.replaceChildren();
    data.profile_details.forEach((item,index)=>{
      const row=document.createElement('div');row.className='profile-detail-row';row.dataset.detailId=item.id;
      row.append(makeDetailInput('Heading shown on page',item.label,'label'),makeDetailInput('Detail text',item.value,'value'),makeDetailInput('Link (optional)',item.url,'url','url'));
      const actions=document.createElement('div');actions.className='profile-detail-actions';
      const show=document.createElement('label');show.className='section-visible';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=item.visible!==false;checkbox.dataset.profileDetailVisible='';const showText=document.createElement('span');showText.textContent='Show on profile';show.append(checkbox,showText);
      const remove=document.createElement('button');remove.type='button';remove.className='button danger';remove.textContent='Remove detail';remove.addEventListener('click',()=>{data.profile_details.splice(index,1);renderProfileDetails()});
      actions.append(show,remove);row.append(actions);profileDetailFields.append(row);
    });
  };
  renderProfileDetails();
  document.getElementById('add-profile-detail').addEventListener('click',()=>{data.profile_details.push({id:`detail_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,7)}`,label:'New detail',value:'',url:'',visible:true});renderProfileDetails();profileDetailFields.lastElementChild?.scrollIntoView({behavior:'smooth',block:'center'})});
  const readSocialLinks=()=>[...socialLinkFields.querySelectorAll('.social-link-row')].map(row=>({title:row.querySelector('[data-key="social_title"]')?.value.trim()||'',url:row.querySelector('[data-key="social_url"]')?.value.trim()||''}));
  const renderSocialLinks=()=>{socialLinkFields.replaceChildren();data.social_links.forEach((item,index)=>{const row=document.createElement('div');row.className='social-link-row';row.append(makeField('social_title',item.title||''),makeField('social_url',item.url||''));const slot=document.createElement('span');slot.className='social-link-slot';slot.textContent=`Link ${index+1}`;const remove=document.createElement('button');remove.type='button';remove.className='button danger social-link-remove';remove.textContent='Remove';remove.addEventListener('click',()=>{data.social_links=readSocialLinks();data.social_links.splice(index,1);renderSocialLinks()});row.prepend(slot);row.append(remove);socialLinkFields.append(row)})};
  renderSocialLinks();
  document.getElementById('add-social-link').addEventListener('click',()=>{data.social_links=readSocialLinks();data.social_links.push({title:'',url:''});renderSocialLinks();socialLinkFields.lastElementChild?.scrollIntoView({behavior:'smooth',block:'center'});socialLinkFields.lastElementChild?.querySelector('[data-key="social_title"]')?.focus()});
  const supervisionCategoryFields=document.getElementById('supervision-category-fields');
  const readSupervisionCategories=()=>[...supervisionCategoryFields.querySelectorAll('[data-supervision-category]')].map(input=>input.value.trim());
  const renderSupervisionCategories=()=>{
    supervisionCategoryFields.replaceChildren();
    data.supervision_categories.forEach((category,index)=>{
      const row=document.createElement('div');row.className='supervision-category-row';
      const field=makeField('category',category);field.querySelector('span').textContent='Category name';const input=field.querySelector('[data-key="category"]');input.dataset.supervisionCategory='';row.append(field);
      const remove=document.createElement('button');remove.type='button';remove.className='button danger';remove.textContent='Remove category';remove.addEventListener('click',()=>{
        const oldName=input.value.trim();const assigned=data.supervision.some(item=>item.category===oldName);
        if(assigned&&!window.confirm(`Move students in “${oldName}” to another category before removing it?`))return;
        data.supervision_categories=readSupervisionCategories();data.supervision_categories.splice(index,1);
        if(assigned){let target=data.supervision_categories[0];if(!target){target='Other Students';data.supervision_categories.push(target)}data.supervision.forEach(item=>{if(item.category===oldName)item.category=target})}
        renderSupervisionCategories();renderCollection('supervision');
      });row.append(remove);supervisionCategoryFields.append(row);
    });
  };
  renderSupervisionCategories();
  document.getElementById('add-supervision-category').addEventListener('click',()=>{data.supervision_categories=readSupervisionCategories();data.supervision_categories.push('New category');renderSupervisionCategories();supervisionCategoryFields.lastElementChild?.querySelector('input')?.focus()});
  supervisionCategoryFields.addEventListener('input',event=>{
    if(!event.target.matches('[data-supervision-category]'))return;
    const row=event.target.closest('.supervision-category-row');const index=[...supervisionCategoryFields.children].indexOf(row);const previous=data.supervision_categories[index];
    data.supervision_categories[index]=event.target.value;
    if(previous)data.supervision.forEach(item=>{if(item.category===previous)item.category=event.target.value});
    renderCollection('supervision');
  });
  const itemText=item=>typeof item==='string'?item:(item.title||item.student_name||item.student||item.degree||item.role||item.name||item.kind||'New entry');
  function renderCollection(key){
    const host=document.getElementById(`entries-${key}`);host.replaceChildren();
    data[key].forEach((item,index)=>{
      const details=document.createElement('details');details.className='entry';details.open=index===0;const summary=document.createElement('summary');summary.textContent=`${index+1}. ${itemText(item)}`;details.append(summary);
      const fields=document.createElement('div');fields.className='entry-fields';
      if(typeof item==='string')fields.append(makeField('value',item));else Object.entries(item).forEach(([field,value])=>{if(field!=='image')fields.append(makeField(field,value))});
      const media=document.createElement('div');media.className='entry-image-editor';
      if(item.image){const img=document.createElement('img');img.src=item.image;img.alt='Current image';media.append(img)}
      const label=document.createElement('label');label.className='field';const cap=document.createElement('span');cap.textContent=key==='publications'?'Optional book / publication cover':'Optional image';const upload=document.createElement('input');upload.type='file';upload.accept='image/jpeg,image/png,image/webp';upload.name=`entry_images[${key}][${index}]`;label.append(cap,upload);media.append(label);
      const help=document.createElement('small');help.textContent='Optional JPG, PNG, or WebP image, up to 5 MB.';media.append(help);
      if(item.image){const removeImage=document.createElement('button');removeImage.type='button';removeImage.className='button secondary';removeImage.textContent='Remove current image';removeImage.addEventListener('click',()=>{item.image='';renderCollection(key)});media.append(removeImage)}
      fields.append(media);fields.addEventListener('input',()=>{const input=fields.querySelector('input[data-key="title"],input[data-key="student_name"],input[data-key="student"],input[data-key="degree"],input[data-key="role"],input[data-key="name"],input[data-key="value"]');if(input)summary.textContent=`${index+1}. ${input.value||'New entry'}`});
      const remove=document.createElement('button');remove.type='button';remove.className='button danger';remove.textContent='Remove this entry';remove.addEventListener('click',()=>{data[key].splice(index,1);renderCollection(key)});details.append(fields,remove);host.append(details);
    });
  }
  for(const key of Object.keys(collectionNames)){
    renderCollection(key);const button=document.querySelector(`[data-section="${key}"] .add-entry`);
    button.addEventListener('click',()=>{const next=JSON.parse(JSON.stringify(starter[key]));if(key==='supervision')next.category=readSupervisionCategories()[0]||'';data[key].push(next);renderCollection(key);document.querySelector(`#entries-${key} .entry:last-child`)?.scrollIntoView({behavior:'smooth',block:'center'})});
  }
  const sectionSettings=document.getElementById('section-settings');data.section_settings=data.section_settings||{};
  for(const [key,title] of Object.entries(settingsNames))if(key!=='research_interests'){
    data.section_settings[key]=data.section_settings[key]||{title,visible:true};const entry=data.section_settings[key];
    const row=document.createElement('div');row.className='section-setting-row';const name=makeField('section_title',entry.title||title);name.querySelector('span').textContent=`${title} heading`;name.querySelector('[data-key]').dataset.settingTitle=key;
    const visible=document.createElement('label');visible.className='section-visible';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=entry.visible!==false;checkbox.dataset.settingVisible=key;const label=document.createElement('span');label.textContent='Show on portfolio';visible.append(checkbox,label);const actions=document.createElement('div');actions.className='section-setting-actions';const remove=document.createElement('button');remove.type='button';remove.className='button secondary';remove.textContent='Remove from page';remove.addEventListener('click',()=>{checkbox.checked=false;data.section_settings[key].visible=false});actions.append(visible,remove);row.append(name,actions);sectionSettings.append(row);
  }
  const orderList=document.getElementById('section-order-list'),customEditors=document.getElementById('custom-section-editors');
  const orderable=()=>[...Object.keys(collectionNames).filter(key=>key!=='research_interests'),...data.custom_sections.map(item=>item.id)];
  data.section_order=(Array.isArray(data.section_order)?data.section_order:[]).filter(key=>orderable().includes(key));
  for(const key of orderable())if(!data.section_order.includes(key))data.section_order.push(key);
  const sectionName=key=>data.custom_sections.find(item=>item.id===key)?.title||data.section_settings[key]?.title||collectionNames[key]||'New custom section';
  const renderOrder=()=>{orderList.replaceChildren();data.section_order.forEach((key,index)=>{const row=document.createElement('li');row.className='section-order-item';const name=document.createElement('span');name.textContent=`${index+1}. ${sectionName(key)}`;const controls=document.createElement('div');controls.className='order-controls';for(const [symbol,delta,title] of [['↑',-1,'Move up'],['↓',1,'Move down']]){const button=document.createElement('button');button.type='button';button.className='button secondary';button.textContent=symbol;button.title=title;button.disabled=index+delta<0||index+delta>=data.section_order.length;button.addEventListener('click',()=>{const next=index+delta;[data.section_order[index],data.section_order[next]]=[data.section_order[next],data.section_order[index]];renderOrder()});controls.append(button)}row.append(name,controls);orderList.append(row)})};
  const renderCustomSections=()=>{
    customEditors.replaceChildren();
    data.custom_sections.forEach((item,index)=>{
      const card=document.createElement('details');card.className='custom-editor-card';card.open=true;const summary=document.createElement('summary');summary.textContent=item.title||`New section ${index+1}`;card.append(summary);
      const fields=document.createElement('div');fields.className='entry-fields';fields.append(makeField('title',item.title||''),makeField('detail',item.detail||''),makeField('url',item.url||''),makeField('image_alt',item.image_alt||''));
      const media=document.createElement('div');media.className='entry-image-editor';if(item.image){const img=document.createElement('img');img.src=item.image;img.alt='Current section image';media.append(img)}
      const imageLabel=document.createElement('label');imageLabel.className='field';const imageCaption=document.createElement('span');imageCaption.textContent='Optional section image';const upload=document.createElement('input');upload.type='file';upload.accept='image/jpeg,image/png,image/webp';upload.name=`entry_images[custom_sections][${index}]`;imageLabel.append(imageCaption,upload);media.append(imageLabel);
      if(item.image){const clear=document.createElement('button');clear.type='button';clear.className='button secondary';clear.textContent='Remove image';clear.addEventListener('click',()=>{item.image='';renderCustomSections()});media.append(clear)}
      fields.append(media);fields.addEventListener('input',event=>{const key=event.target.dataset.key;if(key)item[key]=event.target.value;const title=item.title||'';summary.textContent=title||`New section ${index+1}`;renderOrder()});
      const visible=document.createElement('label');visible.className='section-visible custom-visible';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=item.visible!==false;checkbox.addEventListener('change',()=>item.visible=checkbox.checked);const visibleText=document.createElement('span');visibleText.textContent='Show on portfolio';visible.append(checkbox,visibleText);
      const remove=document.createElement('button');remove.type='button';remove.className='button danger';remove.textContent='Delete this section';remove.addEventListener('click',()=>{data.custom_sections.splice(index,1);data.section_order=data.section_order.filter(key=>key!==item.id);renderCustomSections();renderOrder()});card.append(fields,visible,remove);customEditors.append(card);
    });
  };
  const addSection=document.getElementById('add-custom-section');addSection.addEventListener('click',()=>{const id=`custom_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,7)}`;data.custom_sections.push({id,title:'New section',detail:'',url:'',image:'',image_alt:'',visible:true});data.section_order.push(id);renderCustomSections();renderOrder();customEditors.lastElementChild?.scrollIntoView({behavior:'smooth',block:'center'})});
  renderCustomSections();renderOrder();
  sectionSettings.addEventListener('input',event=>{const key=event.target.dataset.settingTitle;if(key){data.section_settings[key].title=event.target.value;renderOrder()}});
  sectionSettings.addEventListener('change',event=>{const key=event.target.dataset.settingVisible;if(key)data.section_settings[key].visible=event.target.checked});
  const photo=document.getElementById('profile-image');photo.addEventListener('change',()=>{if(photo.files[0])document.getElementById('current-photo').src=URL.createObjectURL(photo.files[0])});
  document.getElementById('portfolio-form').addEventListener('submit',event=>{
    event.preventDefault();profileFields.querySelectorAll('[data-key]').forEach(input=>data.profile[input.dataset.key]=input.value);
    data.profile_details=[...profileDetailFields.querySelectorAll('.profile-detail-row')].map(row=>{const get=key=>row.querySelector(`[data-profile-detail="${key}"]`)?.value.trim()||'';return{id:row.dataset.detailId,label:get('label'),value:get('value'),url:get('url'),visible:row.querySelector('[data-profile-detail-visible]').checked}});
    const legacyKeys={designation:'role',department:'department',institution:'institution',additional_appointment:'additional_role',location:'location'};
    for(const [detailId,profileKey] of Object.entries(legacyKeys))data.profile[profileKey]=data.profile_details.find(item=>item.id===detailId)?.value||'';
    data.social_links=readSocialLinks().filter(item=>item.title||item.url);
    for(const key of Object.keys(collectionNames))data[key]=[...document.querySelectorAll(`#entries-${key} .entry`)].map((entry,index)=>{const inputs=[...entry.querySelectorAll('[data-key]')];const next=Object.fromEntries(inputs.map(input=>[input.dataset.key,input.value]));const old=data[key][index];if(old?.image)next.image=old.image;return next});
    data.supervision_categories=uniqueNames([...readSupervisionCategories(),...data.supervision.map(item=>item.category)]);
    data.custom_sections=[...customEditors.querySelectorAll('.custom-editor-card')].map((card,index)=>{const old=data.custom_sections[index]||{};const next=Object.fromEntries([...card.querySelectorAll('[data-key]')].map(input=>[input.dataset.key,input.value]));next.id=old.id;next.visible=old.visible!==false;if(old.image)next.image=old.image;return next});
    sectionSettings.querySelectorAll('[data-setting-title]').forEach(input=>data.section_settings[input.dataset.settingTitle].title=input.value);
    sectionSettings.querySelectorAll('[data-setting-visible]').forEach(input=>data.section_settings[input.dataset.settingVisible].visible=input.checked);
    document.getElementById('content-json').value=JSON.stringify(data);event.target.submit();
  });
})();
