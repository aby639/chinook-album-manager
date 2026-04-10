const puppeteer = require("puppeteer");
const path = require("path");

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();

  const htmlPath = "file://" + path.join(__dirname, "user_documentation_revised.html");

  await page.goto(htmlPath, { waitUntil: "networkidle0" });

  await page.pdf({
    path: path.join(__dirname, "user_documentation_final.pdf"),
    format: "A4",
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: `<div></div>`,
    footerTemplate: `
      <div style="width:100%; font-size:10px; padding:0 12mm; color:#555; text-align:right;">
        Page <span class="pageNumber"></span> of <span class="totalPages"></span>
      </div>
    `,
    margin: {
      top: "12mm",
      right: "12mm",
      bottom: "16mm",
      left: "12mm"
    }
  });

  await browser.close();
})();