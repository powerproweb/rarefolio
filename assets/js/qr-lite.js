/* qdQrLite: minimal QR generator for URLs (canvas).
   Uses a compact embedded generator (subset of Nayuki QR, MIT).
*/
(function (global) {
  function utf8ToBytes(str){return new TextEncoder().encode(str);}
  const qrcodegen=(function(){
    "use strict";
    function QrCode(version, errCorLvl, dataCodewords, mask){
      this.version=version;this.errorCorrectionLevel=errCorLvl;this.mask=mask;
      const size=version*4+17;this.size=size;
      const modules=Array.from({length:size},()=>Array.from({length:size},()=>false));
      const isFunction=Array.from({length:size},()=>Array.from({length:size},()=>false));
      this.modules=modules;this.isFunction=isFunction;
      drawFunctionPatterns(this);
      const all=addEccAndInterleave(version,errCorLvl,dataCodewords);
      drawCodewords(this,all);
      let m=mask;
      if(m===-1){
        let best=1e9;
        for(let i=0;i<8;i++){
          applyMask(this,i);drawFormatBits(this,errCorLvl,i);
          const p=getPenaltyScore(this);
          applyMask(this,i);
          if(p<best){best=p;m=i;}
        }
      }
      applyMask(this,m);drawFormatBits(this,errCorLvl,m);
    }
    QrCode.Ecc={LOW:0,MEDIUM:1,QUARTILE:2,HIGH:3};
    QrCode.prototype.getModule=function(x,y){return this.modules[y][x];};

    function encodeText(text,ecl){
      const bytes=utf8ToBytes(text);
      return encodeBytes(bytes,ecl);
    }
    function encodeBytes(bytes,ecl){
      for(let ver=1;ver<=10;ver++){
        const cap=getCapacity(ver,ecl);
        if(bytes.length<=cap){
          const data=makeDataCodewords(ver,ecl,bytes);
          return new QrCode(ver,ecl,data,-1);
        }
      }
      const data=makeDataCodewords(10,ecl,bytes.slice(0,getCapacity(10,ecl)));
      return new QrCode(10,ecl,data,-1);
    }
    function getCapacity(ver,ecl){
      const caps={1:[17,14,11,7],2:[32,26,20,14],3:[53,42,32,24],4:[78,62,46,34],5:[106,84,60,44],6:[134,106,74,58],7:[154,122,86,64],8:[192,152,108,84],9:[230,180,130,98],10:[271,213,151,119]};
      return caps[ver][ecl];
    }
    function getNumDataCodewords(ver,ecl){
      const table={1:[19,16,13,9],2:[34,28,22,16],3:[55,44,34,26],4:[80,64,48,36],5:[108,86,62,46],6:[136,108,76,60],7:[156,124,88,66],8:[194,154,110,86],9:[232,182,132,100],10:[274,216,154,122]};
      return table[ver][ecl];
    }
    function makeDataCodewords(ver,ecl,bytes){
      const bb=[];
      function pushBits(val,len){for(let i=len-1;i>=0;i--) bb.push(((val>>>i)&1)!==0);}
      pushBits(0x4,4);
      const lenBits=ver<=9?8:16;
      pushBits(bytes.length,lenBits);
      for(const b of bytes) pushBits(b,8);
      const totalBits=getNumDataCodewords(ver,ecl)*8;
      const term=Math.min(4,totalBits-bb.length);
      for(let i=0;i<term;i++) bb.push(false);
      while(bb.length%8!==0) bb.push(false);
      const codewords=[];
      for(let i=0;i<bb.length;i+=8){
        let cw=0;for(let j=0;j<8;j++) cw=(cw<<1)|(bb[i+j]?1:0);
        codewords.push(cw);
      }
      const pad=[0xEC,0x11];let k=0;
      while(codewords.length<getNumDataCodewords(ver,ecl)) codewords.push(pad[k++&1]);
      return codewords;
    }

    // GF(256) for RS ECC
    const GF_EXP=new Array(512);const GF_LOG=new Array(256);
    (function(){let x=1;for(let i=0;i<255;i++){GF_EXP[i]=x;GF_LOG[x]=i;x<<=1;if(x&0x100) x^=0x11D;}for(let i=255;i<512;i++) GF_EXP[i]=GF_EXP[i-255];})();
    function gfMul(a,b){if(a===0||b===0) return 0;return GF_EXP[GF_LOG[a]+GF_LOG[b]];}
    function polyMul(p,q){const r=new Array(p.length+q.length-1).fill(0);for(let i=0;i<p.length;i++)for(let j=0;j<q.length;j++) r[i+j]^=gfMul(p[i],q[j]);return r;}
    function reedSolomonCompute(data,eccLen){let gen=[1];for(let i=0;i<eccLen;i++) gen=polyMul(gen,[1,GF_EXP[i]]);let res=new Array(eccLen).fill(0);
      for(const b of data){const factor=b^res[0];res.shift();res.push(0);for(let j=0;j<eccLen;j++) res[j]^=gfMul(gen[j+1],factor);}return res;}

    function eccCodewordsPerBlock(ver,ecl){const t={1:[7,10,13,17],2:[10,16,22,28],3:[15,26,36,44],4:[20,36,52,64],5:[26,48,72,88],6:[36,64,96,112],7:[40,72,108,130],8:[48,88,132,156],9:[60,110,160,192],10:[72,130,192,224]};return t[ver][ecl];}
    function numErrorCorrectionBlocks(ver,ecl){const t={1:[1,1,1,1],2:[1,1,1,1],3:[1,1,2,2],4:[1,2,2,4],5:[1,2,4,4],6:[2,4,4,4],7:[2,4,6,5],8:[2,4,6,6],9:[2,5,8,8],10:[4,5,8,8]};return t[ver][ecl];}
    function addEccAndInterleave(ver,ecl,data){
      const eccLen=eccCodewordsPerBlock(ver,ecl);const blocks=numErrorCorrectionBlocks(ver,ecl);
      const shortLen=Math.floor(data.length/blocks);const numLong=data.length%blocks;
      const parts=[];let k=0;
      for(let i=0;i<blocks;i++){
        const len=shortLen+(i<numLong?1:0);
        const d=data.slice(k,k+len);k+=len;
        const ecc=reedSolomonCompute(d,eccLen);
        parts.push({d,ecc});
      }
      const out=[];
      const maxData=Math.max(...parts.map(p=>p.d.length));
      for(let i=0;i<maxData;i++) for(const p of parts) if(i<p.d.length) out.push(p.d[i]);
      for(let i=0;i<eccLen;i++) for(const p of parts) out.push(p.ecc[i]);
      return out;
    }

    function setFunction(qr,x,y,val){qr.modules[y][x]=val;qr.isFunction[y][x]=true;}
    function drawFinder(qr,x,y){for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const xx=x+dx,yy=y+dy;if(xx<0||yy<0||xx>=qr.size||yy>=qr.size) continue;const dist=Math.max(Math.abs(dx),Math.abs(dy));setFunction(qr,xx,yy,dist!==2&&dist!==4);}}
    function drawFunctionPatterns(qr){const size=qr.size;drawFinder(qr,3,3);drawFinder(qr,size-4,3);drawFinder(qr,3,size-4);
      for(let i=0;i<size;i++){setFunction(qr,6,i,i%2===0);setFunction(qr,i,6,i%2===0);}setFunction(qr,8,size-8,true);
      for(let i=0;i<9;i++){setFunction(qr,8,i,false);setFunction(qr,i,8,false);}for(let i=0;i<8;i++){setFunction(qr,size-1-i,8,false);setFunction(qr,8,size-1-i,false);}setFunction(qr,8,8,false);setFunction(qr,8,size-8,false);setFunction(qr,size-8,8,false);
    }
    function drawCodewords(qr,codewords){let i=0;let dir=-1;for(let x=qr.size-1;x>=1;x-=2){if(x===6)x--;for(let y=(dir===-1?qr.size-1:0);(dir===-1?y>=0:y<qr.size);y+=dir){for(let k=0;k<2;k++){const xx=x-k;if(qr.isFunction[y][xx]) continue;let bit=false;if(i<codewords.length*8){bit=((codewords[Math.floor(i/8)]>>> (7-(i%8)))&1)!==0;i++;}qr.modules[y][xx]=bit;}}dir=-dir;}}
    function drawFormatBits(qr,ecl,mask){const data=(ecl<<3)|mask;let rem=data;for(let i=0;i<10;i++) rem=(rem<<1)^(((rem>>>9)&1)!==0?0x537:0);const bits=((data<<10)|rem)^0x5412;
      for(let i=0;i<=5;i++){qr.modules[8][i]=((bits>>>i)&1)!==0;qr.isFunction[8][i]=true;}
      qr.modules[8][7]=((bits>>>6)&1)!==0;qr.isFunction[8][7]=true;
      qr.modules[8][8]=((bits>>>7)&1)!==0;qr.isFunction[8][8]=true;
      qr.modules[7][8]=((bits>>>8)&1)!==0;qr.isFunction[7][8]=true;
      for(let i=9;i<15;i++){qr.modules[14-i][8]=((bits>>>i)&1)!==0;qr.isFunction[14-i][8]=true;}
      for(let i=0;i<8;i++){qr.modules[qr.size-1-i][8]=((bits>>>i)&1)!==0;qr.isFunction[qr.size-1-i][8]=true;}
      for(let i=8;i<15;i++){qr.modules[8][qr.size-15+i]=((bits>>>i)&1)!==0;qr.isFunction[8][qr.size-15+i]=true;}
      qr.modules[8][qr.size-8]=true;qr.isFunction[8][qr.size-8]=true;
    }
    function applyMask(qr,mask){for(let y=0;y<qr.size;y++)for(let x=0;x<qr.size;x++){if(qr.isFunction[y][x]) continue;let inv=false;switch(mask){case 0:inv=(x+y)%2===0;break;case 1:inv=y%2===0;break;case 2:inv=x%3===0;break;case 3:inv=(x+y)%3===0;break;case 4:inv=(Math.floor(y/2)+Math.floor(x/3))%2===0;break;case 5:inv=(x*y)%2+(x*y)%3===0;break;case 6:inv=((x*y)%2+(x*y)%3)%2===0;break;case 7:inv=((x+y)%2+(x*y)%3)%2===0;break;}if(inv) qr.modules[y][x]=!qr.modules[y][x];}}
    function getPenaltyScore(qr){let p=0;const size=qr.size;for(let y=0;y<size;y++){let c=qr.modules[y][0],len=1;for(let x=1;x<size;x++){if(qr.modules[y][x]===c) len++; else {if(len>=5)p+=3+(len-5);c=qr.modules[y][x];len=1;}}if(len>=5)p+=3+(len-5);}for(let x=0;x<size;x++){let c=qr.modules[0][x],len=1;for(let y=1;y<size;y++){if(qr.modules[y][x]===c) len++; else {if(len>=5)p+=3+(len-5);c=qr.modules[y][x];len=1;}}if(len>=5)p+=3+(len-5);}return p;}

    return {QrCode,encodeText};
  })();

  function drawQrToCanvas(canvas,text){
    const qr=qrcodegen.encodeText(text,qrcodegen.QrCode.Ecc.LOW);
    const size=qr.size;const scale=8;
    canvas.width=size*scale;canvas.height=size*scale;
    const ctx=canvas.getContext("2d");
    ctx.fillStyle="#fff";ctx.fillRect(0,0,canvas.width,canvas.height);
    ctx.fillStyle="#000";
    for(let y=0;y<size;y++)for(let x=0;x<size;x++) if(qr.getModule(x,y)) ctx.fillRect(x*scale,y*scale,scale,scale);
  }

  global.qdQrLite={drawQrToCanvas};
})(window);
