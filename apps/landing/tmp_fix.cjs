const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, 'src', 'components');
const files = fs.readdirSync(dir).filter(f => f.endsWith('.module.scss'));

files.forEach(file => {
   const p = path.join(dir, file);
   let content = fs.readFileSync(p, 'utf8');
   content = content.replace(/@import '\.\.\/\.\.\/styles\//g, "@import '../styles/");
   fs.writeFileSync(p, content);
});

console.log("Fixed SCSS imports");
