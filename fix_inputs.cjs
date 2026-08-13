const fs = require('fs');
const path = require('path');

const classesToRemove = [
    'rounded-lg', 'rounded-xl',
    'border-gray-300', 'border-gray-200',
    'bg-gray-50/50',
    'px-4', 'px-3', 'py-2.5', 'py-3', 'py-2',
    'text-sm',
    'shadow-sm',
    'transition-all',
    'focus:border-blue-500',
    'focus:bg-white',
    'focus:ring-4', 'focus:ring-blue-500/10', 'focus:ring-blue-500',
    'placeholder:text-gray-400'
];

const classesToAdd = 'rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(function(file) {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) { 
            results = results.concat(walk(file));
        } else { 
            if (file.endsWith('.blade.php')) {
                results.push(file);
            }
        }
    });
    return results;
}

const files = walk('resources/views');
let updatedCount = 0;

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Find class="..." or class='...' attributes
    const classRegex = /class=(["'])(.*?)\1/g;
    let newContent = content.replace(classRegex, (match, quote, classString) => {
        let classes = classString.split(/\s+/).filter(c => c.trim() !== '');
        
        // Only target inputs/selects by checking for border-gray-* and focus:ring-blue-*
        const isTarget = classes.some(c => c === 'border-gray-300' || c === 'border-gray-200') &&
                         classes.some(c => c.startsWith('focus:ring-blue-500'));
                         
        if (isTarget) {
            // Remove old styling classes
            classes = classes.filter(c => !classesToRemove.includes(c));
            
            // Add new styling classes
            classesToAdd.split(' ').forEach(c => {
                if (!classes.includes(c)) {
                    classes.push(c);
                }
            });
            
            return `class=${quote}${classes.join(' ')}${quote}`;
        }
        
        return match;
    });
    
    if (newContent !== content) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log('Updated inputs in ' + file);
        updatedCount++;
    }
});

console.log(`Finished updating ${updatedCount} files.`);
