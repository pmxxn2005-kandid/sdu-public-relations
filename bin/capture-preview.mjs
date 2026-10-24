import {chromium} from 'playwright';
import {mkdirSync} from 'node:fs';
const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{}),args:['--no-sandbox']});
mkdirSync('artifacts',{recursive:true});
try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  await page.goto(process.env.APP_URL||'http://127.0.0.1:8080/',{waitUntil:'networkidle'});
  await page.screenshot({path:'artifacts/website-desktop.png',fullPage:true});
  await page.setViewportSize({width:360,height:850});await page.screenshot({path:'artifacts/website-mobile.png',fullPage:true});
  console.log('Saved artifacts/website-desktop.png and artifacts/website-mobile.png');
}finally{await browser.close();}
