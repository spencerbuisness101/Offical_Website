import re

def minify_js(input_file, output_file):
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Remove single-line comments (but not URLs)
    content = re.sub(r'(?<!:)\/\/.*$', '', content, flags=re.MULTILINE)
    # Remove multi-line comments
    content = re.sub(r'\/\*[\s\S]*?\*\/', '', content)
    # Remove whitespace around operators, but keep spaces around + and - in certain contexts
    # This is a simple minifier - for production use proper tools like terser
    content = re.sub(r'\s+', ' ', content)
    # Remove spaces around common operators (but not in calc() or similar)
    content = re.sub(r'\s*([{}();,:<>])\s*', r'\1', content)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(content.strip())
    
    print(f'Minified {input_file} -> {output_file}')

minify_js('js/index-modal.js', 'js/index-modal.min.js')
minify_js('js/command-palette.js', 'js/command-palette.min.js')
