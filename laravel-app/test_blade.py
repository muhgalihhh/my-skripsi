import re

with open(r'c:\Materi Kuliah\AYO KERJAIN SKRIPSI\- Sistem\laravel-app\resources\views\livewire\jurusan\topic-modeling-manager.blade.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

stack = []
for i, line in enumerate(lines, 1):
    # Find all blade directives
    matches = re.finditer(r'@(if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|php|endphp)\b', line)
    for match in matches:
        m = match.group(1)
        if m in ['if', 'foreach', 'forelse', 'php']:
            stack.append((m, i))
        elif m in ['endif', 'endforeach', 'endforelse', 'endphp']:
            if not stack:
                print(f"Error: Mismatched @{m} at line {i} (stack is empty)")
            else:
                top = stack.pop()
                if (m == 'endif' and top[0] != 'if') or \
                   (m == 'endforeach' and top[0] != 'foreach') or \
                   (m == 'endforelse' and top[0] != 'forelse') or \
                   (m == 'endphp' and top[0] != 'php'):
                      print(f"Error: Mismatch! expected end for {top[0]} (opened at {top[1]}), got @{m} at line {i}")

print(f"Remaining in stack (unclosed): {stack}")
