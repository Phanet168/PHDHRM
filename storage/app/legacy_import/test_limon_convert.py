# -*- coding: utf-8 -*-
import os, sys, json
base = r'c:\xampp\htdocs\PHDHRM\storage\tmp\KhmerConverter\modules'
sys.path.append(base)
os.chdir(base)
import FontDataXML
import unicodeProcess
import unicodeReorder

fd = FontDataXML.FontData()
data = fd.legacyData('limon')

p = r'c:\xampp\htdocs\PHDHRM\storage\app\legacy_import\skill.json'
rows = json.load(open(p, 'rb'))
for i, row in enumerate(rows[:5]):
    legacy = row.get('SkillK')
    if not legacy:
        continue
    legacy_bytes = legacy.encode('cp1252')
    uni = unicodeReorder.reorder(unicodeProcess.process(legacy_bytes, data))
    print('ID=%s LEG=%r UNI=%s' % (row.get('SkillID'), legacy, uni.encode('utf-8')))
