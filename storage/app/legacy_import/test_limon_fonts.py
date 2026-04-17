# -*- coding: utf-8 -*-
import os, sys
base = r'c:\xampp\htdocs\PHDHRM\storage\tmp\KhmerConverter\modules'
sys.path.append(base)
os.chdir(base)
import FontDataXML, unicodeProcess, unicodeReorder
fd = FontDataXML.FontData()
legacy = u'evC¢bNçitÉkeTs'.encode('cp1252')
for font in fd.listFontNames():
    if 'limon' in font.lower():
        data = fd.legacyData(font)
        uni = unicodeReorder.reorder(unicodeProcess.process(legacy, data))
        line = u'%s => %s' % (unicode(font, 'cp1252') if isinstance(font, str) else font, uni)
        sys.stdout.write(line.encode('utf-8') + '\n')
