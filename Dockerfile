FROM ubuntu:latest
LABEL authors="nh846"

ENTRYPOINT ["top", "-b"]