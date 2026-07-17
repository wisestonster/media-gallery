// stdin: {"address": "0x...", "message": "...", "signature": "0x..."}
// stdout: {"valid": true}  또는  {"valid": false, "error": "..."}
const { verifyMessage } = require('ethers');

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { raw += chunk; });
process.stdin.on('end', () => {
  try {
    const { address, message, signature } = JSON.parse(raw);
    if (!address || !message || !signature || !/^0x[0-9a-fA-F]{40}$/.test(address)) {
      throw new Error('invalid input');
    }

    const recovered = verifyMessage(message, signature);
    const valid = recovered.toLowerCase() === address.toLowerCase();
    process.stdout.write(JSON.stringify({ valid }));
  } catch (err) {
    process.stdout.write(JSON.stringify({ valid: false, error: 'verification_error' }));
  }
});
