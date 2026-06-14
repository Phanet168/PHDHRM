text = 'ážŸáŸ’ážáž¶áž“áž—áž¶áž–áž‘áž¶áŸ†áž„áž¢ážŸáŸ‹'
print('orig:', text)
print('ords:', [hex(ord(c)) for c in text])
bytes_utf8 = text.encode('utf-8')
print('bytes_utf8:', bytes_utf8)
for first in ['latin1', 'cp1252']:
    try:
        s1 = bytes_utf8.decode(first)
        print('decode utf8 bytes as', first, ':', s1)
        print('  ords:', [hex(ord(c)) for c in s1])
        try:
            s2 = s1.encode(first).decode('utf-8')
            print('  then encode', first, 'decode utf8:', s2)
        except Exception as e:
            print('  ERROR second', e)
    except Exception as e:
        print('ERROR first', first, e)
