# bg-upbeat.mp3

- **Título**: Ambient synth pad (generado sintéticamente)
- **Autor**: Saberé Marketing — generado con FFmpeg
- **Origen**: sintético. Se pretendía usar una pista CC0 de freepd.com,
  pero el sitio está cerrado (freepd.com muestra "Site Closed"), así que
  se generó un pad ambiente propio de 60 s (senos 220/277/330 Hz + ruido
  rosa filtrado, fades in/out) con:

  ```bash
  ffmpeg -f lavfi -i sine=frequency=220:duration=60 \
         -f lavfi -i sine=frequency=277.18:duration=60 \
         -f lavfi -i sine=frequency=329.63:duration=60 \
         -f lavfi -i anoisesrc=color=pink:duration=60 \
         -filter_complex "[0:a][1:a][2:a]amix=inputs=3,volume=0.35[m];[m][3:a]amix=inputs=2:weights=1 0.04,volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=57:d=3,aformat=sample_rates=44100:channel_layouts=stereo" \
         -c:a libmp3lame -b:a 128k bg-upbeat.mp3
  ```

- **Licencia**: obra propia generada por el proyecto; uso interno libre.
