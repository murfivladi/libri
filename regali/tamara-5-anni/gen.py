import json,os,subprocess,sys,base64,tempfile
name,scene=sys.argv[1],sys.argv[2]; ref=sys.argv[3] if len(sys.argv)>3 else None
style=("Very colorful, joyful children's picture book illustration for a 5-year-old, soft watercolor and gouache, bright pastel rainbow colors, cute rounded shapes, sparkles. "
"Princess Mara: little girl about 5, big brown eyes, long wavy chestnut hair, slightly crooked small golden crown, pink dress. "
"The Butterfly Unicorn: snow-white unicorn, golden horn, rainbow mane. Many colorful butterflies (pink, blue, yellow, purple). No text, no letters. ")
c={"model":"bytedance/seedream-4.5","input":{"prompt":style+scene,"aspect_ratio":"4:3" if name!="cover" else "2:3","output_format":"jpeg"},"async":False}
if ref: c["input"]["images"]=[{"type":"base64","data":base64.b64encode(open(ref,"rb").read()).decode()}]; c["input"]["prompt"]+=" Keep the same characters and style as the reference image."
t=tempfile.mktemp(); json.dump(c,open(t,"w"))
r=subprocess.run(["curl","-sS","-m","300","-X","POST","https://polza.ai/api/v1/media","-H","Authorization: Bearer "+os.environ["POLZA"],"-H","Content-Type: application/json","-d","@"+t],capture_output=True,text=True)
u=json.loads(r.stdout)["data"][0]["url"]; subprocess.run(["curl","-sS","-o",f"immagini/{name}.jpg",u],check=True); print("ok",name)
