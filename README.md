M8s: Moodle in Kubernetes
===========

----

**WARNING**

This documentation is purely for example.  Please take into consideration all resource restraints and deployment options (such as logging) before taking this tutorial into production.  I will be adding bits and pieces over time as I am more familiar with best practices, but also welcome any comments/suggestions as people walkthrough this tutorial!

----

**Table of Contents**:

1. [Getting Started](#getting-started)
2. [Proxy Setup](#proxy)
3. [Postgres Setup](#postgres)
4. [Moodle Setup](#moodle)
5. [TLDR;](#tldr)

----

M8s is a Kubernetes deployment tutorial for Moodle.  There is absolutely an intention to move this to a [Helm Chart](http://helm.sh) however deploying Moodle to Kubernetes was the priority.  In this example I will walk you through setting up an Ingress Proxy, Postgres and finally Moodle in your Kubernetes environment to deploy Moodle.

> Note that the Ingress Proxy used here, [Traefik](traefik.io), is interchangable with any other method you choose.

---

<a name="getting-started"></a>
Getting Started
---------------

**Don't have Kubernetes?**: I suggest starting with [MiniKube](https://github.com/kubernetes/minikube) to get going.

First and foremost, to get the files mentioned in this tutorial clone the [tutorial](https://github.com/jbkc85/moodle-kubernetes-tutorial) repository.  As stated before, I plan on moving M8s to a Helm Chart after I get the initial manifests created.

```sh

$ git clone https://github.com/jbkc85/moodle-kubernetes-tutorial
$ cd moodle-kubernetes-tutorial

```

<a name="proxy"></a>
Proxy Setup
-----------


As mentioned before, we will be using [Traefik](traefik.io).  To get traefik setup, you can just run the following command:

```sh

$ kubectl apply -f m8s/proxy/

```

This will apply a Kubernetes Ingress Provider as well as a WebUI for Traefik.  This is basically taken directly from the [Kubernetes Documentation](https://docs.traefik.io/user-guide/kubernetes/) over at Traefik, so I won't be going too into detail here about it.

* Traefik Ingress: [proxy/traefik.yaml](m8s/proxy/traefik.yaml)
* Traefik WebUI: [proxy/traefik-webui.yaml](m8s/proxy/traefik-webui.yaml)

To verify we have our proxy setup (taken directly from the documentation in Traefik mentioned earlier), simply run the following:

```sh

$ kubectl get pods --namespace=kube-system
NAME                                         READY     STATUS    RESTARTS   AGE
kube-addon-manager-minikube                  1/1       Running   3          29d
kubernetes-dashboard-fhz0w                   1/1       Running   3          29d
tiller-deploy-327544198-dgfaw                1/1       Running   3          29d
traefik-ingress-controller-678226159-q50aw   1/1       Running   0          11s

```
> notice the traefik-ingress-controller and you are good

```sh

$ curl -XGET $(minikube ip)
404 page not found

```
> we get a 404 because no ingress is currently configured for the minikube ip, and therefore nothing is routed.

*Note:* The reason we only create a service and ingress in the ``traefik-webui.yaml`` is because the ``traefik.yaml`` actually starts the WebUI on port 8080 - it just doesn't expose it outside of the internal network.


<a name="postgres"></a>
Postgres Setup
--------------

Postgres is another obvious interchangable part of the m8s setup.  However since the database of Moodle is essential, I will be going a bit more in detail on how to set it up.

### Persistent Volume

**File: m8s/postgres/persistent-volume.yaml**

The first step is getting a persistent volume setup in Kubernetes.  This is important as if you don't create a persistent volume, the data from Postgres can be potentially lost.

```yaml

apiVersion: v1
kind: PersistentVolume
metadata:
  name: local-postgresql-pv
  labels:
    type: local
spec:
  capacity:
    storage: 1Gi
  accessModes:
    - ReadWriteOnce
  hostPath:
    path: /tmp/postgresql

```

Lets go ahead and get the Persistent Volume created:

```sh

$ kubectl apply -f m8s/postgres/persistent-volume.yaml

```

----

### Secrets

Passwords in Kubernetes isn't that difficult if you pass them in through the environment.  However, this isn't secure nor a best practice.  Therefore instead of making an 'easy tutorial', we are going to go through the creation of secrets.  For this particular setup, we use four secrets: Root Password, Database, Username and Password.  To create them, we basically upload them to the API through the 'secrets' type from plain text files.

If you wish to create your own text files, simply use the following:

```sh

# create txt files if you wish
$ echo -n "your_value" > m8s/postgres/postgres-rootpassword.txt
$ echo -n "your_value" > m8s/postgres/postgres-database.txt
$ echo -n "your_value" > m8s/postgres/postgres-user.txt
$ echo -n "your_value" > m8s/postgres/postgres-password.txt

```

If you wish to use the default (everything is ``moodle``), just leave the files as they are.

Whichever you choose, its now time to upload these secrets.  Once again, to read more about Secrets [just visit the documentation site](https://kubernetes.io/docs/user-guide/secrets/).

```sh

$ kubectl create secret generic postgres-credentials --from-file=m8s/postgres/postgres-rootpassword.txt --from-file=m8s/postgres/postgres-database.txt --from-file=m8s/postgres/postgres-user.txt --from-file=m8s/postgres/postgres-password.txt

```

After the secret is created, you should be able to describe it based on the name given, in our case ``postgres-credentials``.

```sh

$ kubectl describe secret postgres-credentials
Name:   postgres-credentials
Namespace:  default
Labels:   <none>
Annotations:  <none>

Type: Opaque

Data
====
postgres-database.txt:    6 bytes
postgres-password.txt:    6 bytes
postgres-rootpassword.txt:  6 bytes
postgres-user.txt:    6 bytes

```

Now we have our secrets, and we can get onto the Deployment!

----

### Deployment

**File: m8s/postgres/deployment.yaml**

The deployment of Postgres in Kubernetes requires a few pieces to operate as we want it to.  Though they all are organized in the same YAML file, I will show each one in detail here.

First, we have our a service.  The service allows for communication to the pods under the ``selector`` metadata.  To learn more about services in Kubernetes, simply [read the docs for services](https://kubernetes.io/docs/user-guide/services/).

> Please note that this port is exposed only internally to the pods in the namespace we created.

```yaml

apiVersion: v1
kind: Service
metadata:
  name: moodle-postgresql
  labels:
    app: moodle
spec:
  ports:
    - port: 5432
  selector:
    app: moodle
    tier: postgresql
  clusterIP: None

```

Next, we have to claim our Persistent Volume, which you created in the first step.  In some cases, a Persistent Volume can only have a certain amount of claims.  So, in this particular example we are making our claim!

Once again, to read more about [Persistent Volumes](https://kubernetes.io/docs/user-guide/persistent-volumes/), go to the Kubernetes Docs!

```yaml

apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: postgresql-claim
  labels:
    app: moodle
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 1Gi

```

Finally, we get to the deployment.

```yaml

apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: moodle-postgresql
  labels:
    app: moodle
spec:
  strategy:
    type: Recreate
  template:
    metadata:
      labels:
        app: moodle
        tier: postgresql
    spec:
      containers:
      - image: postgres:9.5-alpine
        name: database
        env:
        - name: ROOT_PASSWORD
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-rootpassword.txt
        - name: DATABASE
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-database.txt
        - name: USER
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-username.txt
        - name: PASSWORD
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-password.txt
        ports:
        - containerPort: 5432
          name: postgresql
        volumeMounts:
        - name: postgresql-persistent-storage
          mountPath: /var/lib/postgresql/
      volumes:
      - name: postgresql-persistent-storage
        persistentVolumeClaim:
          claimName: postgresql-claim

```

To deploy, simply use the same ``kubectl apply`` command we have been using:

```sh

$ kubectl apply -f m8s/postgres/deployment.yaml

```

<a name='moodle'></a>
Moodle
------

Now what we have all been waiting for, the deployment of Moodle.  In this example, Moodle will be utilizing the following resources in Kubernetes:

* [Persistent Volume](https://kubernetes.io/docs/user-guide/persistent-volumes/): Once again, the Persistent Volume is used to ensure our 'moodledata' is not erased on accident when/if this group of pods are destroyed. In production deployments, I would highly suggest looking into alternative methods other than Host-based Mounting, but as this is an example it is what I am using. [skip to PersistentVolumes]()
* [Service](https://kubernetes.io/docs/user-guide/services/): As mentioned before Services expose underlying pods in a given namespace. [skip to Services]()
* [Ingress](https://kubernetes.io/docs/user-guide/ingress/): An ingress is an instruction to inform Kubernetes (also Traefik in our tutorial) on how to route incoming traffic. [skip to Ingress]()
* [ConfigMap](https://kubernetes.io/docs/user-guide/configmap/): ConfigMaps are as they sound, a method of storing configurations in Kubernetes. Please remember to read about them and security implications before using them in Production! [skip to configmap]()
* [Deployment](https://kubernetes.io/docs/user-guide/deployments/): Providing metadata to spin up pods and replica sets in Kubernetes. [skip to Deployment]()

### Persistent Volume


Again, creating the persitent-volume is pretty straight forward.

```yaml

apiVersion: v1
kind: PersistentVolume
metadata:
  name: local-moodledata-pv
  labels:
    type: local
spec:
  capacity:
    storage: 2Gi
  accessModes:
    - ReadWriteMany
  hostPath:
    path: /tmp/moodledata

```

Because we are using a persistent volume on the hostPath, we are going to have to create it manually and adjust some permissions to ensure its readable/writable by ``www-data``, our Moodle user.

```sh

$ minikube ssh mkdir /tmp/moodledata
$ minikube ssh sudo chown 33:33 /tmp/moodledata
$ kubectl apply -f m8s/moodle/persistent-volume.yaml

```

Check our work:

```sh

$ kubectl describe pv local-moodledata-pv
Name:		local-moodledata-pv
Labels:		type=local
Status:		Available
Claim:
Reclaim Policy:	Retain
Access Modes:	RWO
Capacity:	2Gi
Message:
Source:
    Type:	HostPath (bare host directory volume)
    Path:	/tmp/moodledata

```

### Service and Ingress


Now we want to create a service and Ingress for the underlying Moodle deployment.  Note we are doing this first before any deployment is created.

#### Service

```yaml

apiVersion: v1
kind: Service
metadata:
  name: moodle
  labels:
    app: moodle
spec:
  ports:
    - port: 80
      targetPort: 80
  selector:
    app: moodle
    tier: frontend

```

> Note: If you want to use SSL, you would still only expose port 80 on this device.  Port 443 would be exposed on the proxy which would be responsible for all SSL transactions while the backend can still simply listen on 80.


#### Ingress

For this ingress, we can use the following host map to access Moodle once brought up in the cluster:

``$(minikube ip) : http://moodle.local``

This is due to the fact our rules in the Ingress map to 'moodle.local', which then will map to oour backend service (under the backend serviceName metadata).

```yaml

apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  name: moodle-ingress
spec:
  rules:
  - host: moodle.local
    http:
      paths:
      - backend:
          serviceName: moodle
          servicePort: 80

```


```sh

$ kubectl apply -f m8s/moodle/service.yaml
service "moodle" created
$ kubectl apply -f m8s/moodle/ingress.yaml
ingress "moodle-ingress" created

```

Check our work:

```sh

$ kubectl describe svc moodle
Name:			moodle
Namespace:		default
Labels:			app=moodle
Selector:		app=moodle,tier=frontend
Type:			ClusterIP
IP:			10.0.0.64
Port:			<unset>	80/TCP
Endpoints:		<none>
Session Affinity:	None
$ kubectl describe ingress moodle-ingress
Name:			moodle-ingress
Namespace:		default
Address:
Default backend:	default-http-backend:80 (<none>)
Rules:
  Host		Path	Backends
  ----		----	--------
  moodle.local
    		 	moodle:80 (<none>)
Annotations:
No events.

```

### ConfigMap


As mentioned at the top, using configMap isn't for everyone.  This can certainly be done a bit more securely using an ![ExtraDopeBadge](https://img.shields.io/badge/Hightower-extra%20dope-E5E4E2.svg) tool like [Kelsey Hightower's Konfd](https://github.com/kelseyhightower/konfd) or the likes, so please keep that in mind as we work through this tutorial.

Basically, I am going to take our config.php that we use with Moodle and pump it into configMap.  *If you make changes to any of the above persistent volumes or ingress settings, you will need to make changes in the file used in this configMap*.

```sh

$ kubectl create configmap moodle-site-config --from-file=m8s/moodle/moodle-config.php
configmap "moodle-site-config" created

```

Check our work:

```sh

$ kubectl get configmaps moodle-site-config -o yaml
apiVersion: v1
data:
  moodle-config.php: |
    <?php  // Moodle configuration file

    unset($CFG);
    global $CFG;
    $CFG = new stdClass();

    $CFG->dbtype    = 'pgsql';
    $CFG->dblibrary = 'native';
    $CFG->dbhost    = 'moodle-postgresql';
    $CFG->dbname    = 'moodle';
    $CFG->dbuser    = 'moodle';
    $CFG->dbpass    = 'moodle';
    $CFG->prefix    = 'mdl';
    $CFG->dboptions = array (
      'dbpersist' => 0,
    );

    $CFG->wwwroot  = 'http://moodle.local';
    $CFG->dataroot  = '/moodle/data';
    $CFG->admin     = 'admin';

    $CFG->directorypermissions = 02775;

    $CFG->passwordsaltmain = 'y0uR34l!ySh0uldtU$3-th1sS&lt';

    require_once "/var/www/html/lib/setup.php";
    // There is no php closing tag in this file,
    // it is intentional because it prevents trailing whitespace problems!
kind: ConfigMap
metadata:
  creationTimestamp: 2017-01-13T15:36:38Z
  name: moodle-site-config
  namespace: default
  resourceVersion: "6087"
  selfLink: /api/v1/namespaces/default/configmaps/moodle-site-config
  uid: 12db3f35-d9a6-11e6-9f63-4217ea3347ce

```

Great, we should be good to go for the deployment!


### Deployment


The deployment is identical to the Postgres deployment in many ways - using the PersistentVolumeClaim and basic Deployment kubernetes objects.

```yaml

apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: moodledata-claim
  labels:
    app: moodle
spec:
  accessModes:
    - ReadWriteMany
  resources:
    requests:
      storage: 2Gi
---
apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: moodle
  labels:
    app: moodle
spec:
  strategy:
    type: Recreate
  template:
    metadata:
      labels:
        app: moodle
        tier: frontend
      annotations:
        pod.alpha.kubernetes.io/init-containers: '[
        {
            "name": "moodle-init",
            "image": "alpine:3.5",
            "imagePullPolicy": "IfNotPresent",
            "command": ["sh", "-c", "chown -R 33:33 /moodledata", ";", "chmod 2775 /moodledata"],
            "volumeMounts": [
                {
                  "name": "moodledata",
                  "mountPath": "/moodledata"
                }
            ]
        }
    ]'
    spec:
      containers:
      - image: jbkc85/docker-moodle
        name: moodle
        ports:
        - containerPort: 80
          name: moodle
        resources:
          requests:
            cpu: 300m
            memory: 128Mi
        volumeMounts:
        - name: moodledata
          mountPath: /moodle/data
        - name: config
          mountPath: /moodle/conf
      volumes:
      - name: moodledata
        persistentVolumeClaim:
          claimName: moodledata-claim
      - name: config
        configMap:
          name: moodle-site-config
          items:
          - key: moodle-config.php
            path: config.php

```

Lets fire her up!

```sh

$ kubectl apply -f m8s/moodle/deployment.yaml
persistentvolumeclaim "moodledata-claim" created
deployment "moodle" created

```

Check our work:

```sh

$ kubectl describe deployment moodle
Name:			moodle
Namespace:		default
CreationTimestamp:	Fri, 13 Jan 2017 09:54:39 -0600
Labels:			app=moodle
Selector:		app=moodle,tier=frontend
Replicas:		1 updated | 1 total | 0 available | 1 unavailable
StrategyType:		Recreate
MinReadySeconds:	0
OldReplicaSets:		<none>
NewReplicaSet:		moodle-476540258 (1/1 replicas created)
Events:
  FirstSeen	LastSeen	Count	From				SubobjectPath	Type		Reason			Message
  ---------	--------	-----	----				-------------	--------	------			-------
  19s		19s		1	{deployment-controller }			Normal		ScalingReplicaSet	Scaled up replica set moodle-476540258 to 1

```

<a name='tldr'></a>
TLDR
----

```sh

$ git clone https://github.com/jbkc85/moodle-kubernetes-tutorial
$ cd moodle-kubernetes-tutorial
$ kubectl apply -f m8s/proxy/
service "traefik-web-ui" created
ingress "traefik-web-ui" created
deployment "traefik-ingress-controller" created
$ kubectl create secret generic postgres-credentials --from-file=m8s/postgres/postgres-rootpassword.txt --from-file=m8s/postgres/postgres-database.txt --from-file=m8s/postgres/postgres-user.txt --from-file=m8s/postgres/postgres-password.txt
secret "postgres-credentials" created
$ kubectl apply -f m8s/postgres/
service "moodle-postgresql" created
persistentvolumeclaim "postgresql-claim" created
deployment "moodle-postgresql" created
persistentvolume "local-postgresql-pv" created
$ minikube ssh mkdir /tmp/moodledata
$ minikube ssh sudo chown 33:33 /tmp/moodledata
$ kubectl create configmap moodle-site-config --from-file=m8s/moodle/moodle-config.php
configmap "moodle-site-config" created
$ kubectl apply -f m8s/moodle
persistentvolumeclaim "moodledata-claim" created
deployment "moodle" created
ingress "moodle-ingress" created
persistentvolume "local-moodledata-pv" created
service "moodle" created

```

Ending Notes
-------------

There are many things I need to do to sure up this documentation, but I wanted to share it in case anyone else is looking into it and may have a better solution or deployment already written.

Rather than post comments here, I would highly encourage anyone interested to use Github so I don't have to worry about ReCaptcha or spam.

[Click here for Comments/Suggestions/Issues](https://github.com/jbkc85/moodle-kubernetes-tutorial/issues)
