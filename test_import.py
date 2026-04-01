import traceback
try:
    import transformers
except Exception as e:
    with open('/app/logs/trace.txt','w') as f:
        f.write(traceback.format_exc())
