const fs = require('fs');
const path = require('path');

const mapping = {
  '🎉': '<x-heroicon-o-sparkles class="size-6 inline text-yellow-500" />',
  '🧾': '<x-heroicon-o-receipt-percent class="size-4 inline" />',
  '🕐': '<x-heroicon-o-clock class="size-4 inline" />',
  '📞': '<x-heroicon-o-phone class="size-4 inline" />',
  '✂️': '<x-heroicon-o-scissors class="size-4 inline" />',
  '📸': '<x-heroicon-o-camera class="size-4 inline" />',
  '⚠': '<x-heroicon-o-exclamation-triangle class="size-4 inline" />',
  '📷': '<x-heroicon-o-camera class="size-4 inline" />',
  '🔍': '<x-heroicon-o-magnifying-glass class="size-4 inline" />'
};

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
files.forEach(file => {
  let content = fs.readFileSync(file, 'utf8');
  let changed = false;
  
  if (file.includes('app.blade.php') || file.includes('pemilih-bahasa.blade.php') || file.includes('modal.blade.php') || file.includes('kosong.blade.php') || file.includes('notifikasi.blade.php') || file.includes('buat-pesanan.blade.php')) {
      return;
  }
  
  for (const [emoji, replacement] of Object.entries(mapping)) {
    if (content.includes(emoji)) {
      content = content.split(emoji).join(replacement);
      changed = true;
    }
  }
  
  if (changed) {
    fs.writeFileSync(file, content, 'utf8');
    console.log('Updated ' + file);
  }
});
