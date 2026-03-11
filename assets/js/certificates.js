(function(){
  const $=(s)=>document.querySelector(s);
  const getParam=(n)=>new URLSearchParams(location.search).get(n);

  function setText(id,val){const el=document.getElementById(id);if(!el) return;el.textContent=(val===undefined||val===null||val==='')?'—':String(val);}
  function formatWallet(w){if(!w) return '—';w=String(w).trim();return w.length<=8?w:'…'+w.slice(-8);}

  function badge(state){const b=$('.qd-badge');if(!b) return;b.dataset.state=state; b.querySelector('.state').textContent=state.toUpperCase();}

  async function fetchCert(certId){
    const res=await fetch(`/api/cert/?cert=${encodeURIComponent(certId)}`,{cache:'no-store'});
    if(!res.ok) throw new Error('API fail');
    return await res.json();
  }

  function buildPreview(){
    const cnft=getParam('cnft')||'qd-silver-0000001';
    const bar=getParam('bar')||'E101837';
    const m=cnft.match(/(\d{7})$/);
    const n=m?m[1]:'0000001';
    const certId=`QDCERT-${bar}-${n}`;
    return {
      certId,
      status:'unverified',
      template:getParam('template')||'parchment',
      cnft:{id:cnft,collection:getParam('collection')||'Rarefolio Silver Bar — Series',barSerial:bar,edition:getParam('edition')||`Shard ${parseInt(n,10)} of 40,000`,silverAllocationTroyOz:getParam('silver')||'0.00025'},
      holder:{displayName:getParam('buyer')||'',privacyEnabled:(getParam('privacy')||'1')!=='0',wallet:getParam('wallet')||''},
      chain:{network:getParam('network')||'Cardano',contractAddress:getParam('contract')||'',tokenId:getParam('token')||'',txHash:getParam('tx')||'',blockNumber:getParam('block')||''},
      custody:{vaultRecordId:`QD-VLT-${bar}-AG-${n}`,vaultAddress:'50 CR 356, Shiner, TX 77984',statement:'Custody recorded; verify via QR reference.'}
    };
  }

  function render(data){
    const holder=data.holder?.privacyEnabled ? 'Private Holder' : (data.holder?.displayName||'—');
    setText('certId', data.certId);
    setText('cnftId', data.cnft?.id);
    setText('collection', data.cnft?.collection);
    setText('barSerial', data.cnft?.barSerial);
    setText('edition', data.cnft?.edition);
    setText('silver', `${data.cnft?.silverAllocationTroyOz||'0.00025'} troy oz`);
    setText('holder', holder);
    setText('wallet', formatWallet(data.holder?.wallet));
    setText('vaultId', data.custody?.vaultRecordId);
    setText('vaultAddr', data.custody?.vaultAddress);
    setText('network', data.chain?.network);
    setText('contract', data.chain?.contractAddress);
    setText('token', data.chain?.tokenId);
    setText('tx', data.chain?.txHash);
    setText('block', data.chain?.blockNumber||'—');

    const isVerified = (data.status==='verified');
    badge(isVerified?'verified':'unverified');

    const origin=location.origin;
    const verifyUrl = `${origin}/verify.html?cert=${encodeURIComponent(data.certId)}`;
    const qr = document.getElementById('qr');
    if(qr && window.qdQrLite) window.qdQrLite.drawQrToCanvas(qr, verifyUrl);
    const vlink = document.getElementById('verifyLink');
    if(vlink){vlink.href=verifyUrl; vlink.textContent=verifyUrl;}

    const dlink = document.getElementById('downloadLink');
    if(dlink){
      const url = data.pdf?.downloadUrl || `${origin}/download.php?cert=${encodeURIComponent(data.certId)}`;
      dlink.href=url; dlink.textContent=url;
    }
  }

  async function init(){
    const cert=getParam('cert');
    const preview = !cert || getParam('preview')==='1';
    try{
      if(preview){render(buildPreview());}
      else {render(await fetchCert(cert));}
      const btn=document.getElementById('printBtn');
      if(btn) btn.addEventListener('click',()=>window.print());
    }catch(e){
      console.error(e);
      badge('unverified');
      setText('certId', cert||'—');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
