text = 'ážŸáŸ’ážáž¶áž“áž—áž¶áž–áž‘áž¶áŸ†áž„áž¢ážŸáŸ‹'
print(text.encode('utf-8').decode('latin1').encode('latin1').decode('utf-8'))
