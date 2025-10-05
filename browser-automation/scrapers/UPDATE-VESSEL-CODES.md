# ShipmentLink Vessel Codes

This directory contains the complete mapping of vessel names to their ShipmentLink vessel codes.

## Files

- `shipmentlink-vessel-codes.json` - Complete vessel code mapping (817 vessels)
- `shipmentlink-https-scraper.js` - Scraper that uses these codes

## Updating Vessel Codes

To update the vessel codes, run:

```bash
node -e "
const https = require('https');

async function getAllVessels() {
  return new Promise((resolve, reject) => {
    https.get('https://ss.shipmentlink.com/tvs2/jsp/TVS2_QueryVessel.jsp?vslCode=', (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        const vessels = {};
        const regex = /<option value='([^']+)'[^>]*>([^<]+)<\/option>/g;
        let match;

        while ((match = regex.exec(data)) !== null) {
          const code = match[1].trim();
          const name = match[2].trim();
          if (code && name && code !== '' && name !== '') {
            vessels[name] = code;
          }
        }

        resolve(vessels);
      });
    }).on('error', reject);
  });
}

(async () => {
  const vessels = await getAllVessels();
  const count = Object.keys(vessels).length;
  console.error(\`Found \${count} vessels\`);
  console.log(JSON.stringify(vessels, null, 2));
})();
" > scrapers/shipmentlink-vessel-codes.json
```

Last updated: 2025-10-05 (817 vessels)
