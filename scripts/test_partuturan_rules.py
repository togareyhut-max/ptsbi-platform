import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from services.partuturan import effective_partuturan_code

h = {"gender": "male", "marga": "Samosir", "tarombo_status": "anak"}
w = {"gender": "female", "marga": "Sinurat", "tarombo_status": "boru"}
assert effective_partuturan_code(w, h) == "anak"
b = {"gender": "female", "marga": "Samosir", "tarombo_status": "boru"}
s = {"gender": "male", "marga": "Sitompul", "tarombo_status": "anak"}
assert effective_partuturan_code(s, b) == "boru"
print("all partuturan rules ok")
