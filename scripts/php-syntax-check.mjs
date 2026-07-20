import fs from 'node:fs';
import path from 'node:path';
import parserPackage from 'php-parser';

const parser = new parserPackage.Engine({parser: {php7: true}, ast: {withPositions: true}});

function parseTree(directory) {
  for (const entry of fs.readdirSync(directory, {withFileTypes: true})) {
    const filename = path.join(directory, entry.name);
    if (entry.isDirectory()) parseTree(filename);
    else if (filename.endsWith('.php')) parser.parseCode(fs.readFileSync(filename, 'utf8'), filename);
  }
}

parseTree('source');
parseTree('tests');
console.log('PHP parser syntax check passed.');

