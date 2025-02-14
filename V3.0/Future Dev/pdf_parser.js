const fs = require('fs');
const pdf = require('pdf-parse');

const filename = 'eticket.pdf';
const outputFilename = 'parsed_text.txt'; // Name of the output text file

async function parsePdf(filename) {
    const dataBuffer = fs.readFileSync(filename);
    const data = await pdf(dataBuffer);
    return data.text;
}

async function main() {
    try {
        const text = await parsePdf(filename);
        fs.writeFileSync(outputFilename, text); // Write the parsed text to a file
        console.log(`Parsed text saved to ${outputFilename}`);
    } catch (err) {
        console.error('Error parsing PDF:', err);
    }
}

main();