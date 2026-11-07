from pathlib import Path
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen
import html
out=Path('wp-content/uploads/brand')
blue='#158FE5'; navy='#0D1726'
# Hexagonal research seal with a genuinely transparent vial counterform.
mark='M28 0 56 16V48L28 64 0 48V16Z M18 13V19H38V13Z M22 23V28L18 33V46Q18 51 23 51H33Q38 51 38 46V33L34 28V23Z'
def word(text,font_path,x,baseline,height,tracking):
 font=TTFont(font_path);gs=font.getGlyphSet();cmap=font.getBestCmap(); cap=font['OS/2'].sCapHeight if hasattr(font['OS/2'],'sCapHeight') else 1467
 scale=height/cap; pieces=[]
 for ch in text:
  name=cmap[ord(ch)];pen=SVGPathPen(gs); t=TransformPen(pen,(scale,0,0,-scale,x,baseline));gs[name].draw(t)
  pieces.append(pen.getCommands());x+=(gs[name].width*scale)+tracking
 return ''.join(pieces),x-tracking
p,x=word('PEPTIDE','C:/Windows/Fonts/arialbd.ttf',75,43,24,0.65)
r,end=word('RESEARCH','C:/Windows/Fonts/arial.ttf',x+12,43,24,0.65)
width=round(end+1,2)
def svg(light=False,icon=False):
 w=56 if icon else width
 body=f'<path fill="{blue}" fill-rule="evenodd" d="{mark}"/>'
 if not icon: body+=f'<path fill="{blue}" d="{p}"/><path fill="{"#FFFFFF" if light else navy}" d="{r}"/>'
 return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="64" viewBox="0 0 {w} 64" role="img" aria-labelledby="logo-title"><title id="logo-title">Peptide Research</title>{body}</svg>\n'
for name,args in [('peptide-research-logo.svg',{}),('peptide-research-logo-white.svg',{'light':True}),('peptide-research-mark.svg',{'icon':True})]:
 (out/name).write_text(svg(**args),encoding='utf-8')
print('Outlined SVGs created:',width,'x 64; no text elements, fonts, embedded images or background.')
